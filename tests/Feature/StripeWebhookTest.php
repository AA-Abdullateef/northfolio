<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\LedgerService;
use App\Services\TransactionLifecycleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_phpunit';

    private function pendingDeposit(string $intent = 'pi_test_1', string $amount = '75.00'): Transaction
    {
        $usd = Asset::firstOrCreate(['name' => 'usd'], ['id' => (string) Str::uuid(), 'label' => 'USD', 'active' => true, 'current_price' => 1, 'decimals' => 2]);
        $wallet = Wallet::forceCreate([
            'id' => (string) Str::uuid(),
            'mnemonic_hash' => hash('sha256', Str::random()),
            'fingerprint' => hash('sha256', Str::random()),
            'public_id' => hash('sha256', Str::random()),
        ]);

        return Transaction::create([
            'wallet_id' => $wallet->id, 'asset_id' => $usd->id, 'type' => 'deposit', 'amount' => $amount,
            'status' => 'pending', 'reference' => (string) Str::uuid(), 'external_id' => $intent,
            'meta' => ['source' => 'stripe', 'stripe_payment_intent_id' => $intent],
        ]);
    }

    private function intent(Transaction $tx, array $override = []): array
    {
        return array_replace_recursive([
            'id' => $tx->external_id, 'status' => 'succeeded', 'currency' => 'usd',
            'amount' => 7500, 'amount_received' => 7500, 'metadata' => ['transaction_id' => $tx->id],
        ], $override);
    }

    private function deliver(string $type, array $intent, ?string $secret = self::SECRET, ?int $timestamp = null)
    {
        return $this->deliverRaw(json_encode(['id' => 'evt_' . Str::random(8), 'type' => $type, 'data' => ['object' => $intent]]), $secret, $timestamp);
    }

    private function deliverRaw(string $payload, ?string $secret = self::SECRET, ?int $timestamp = null)
    {
        $t = $timestamp ?? time();
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($secret !== null) {
            $headers['HTTP_Stripe-Signature'] = "t={$t},v1=" . hash_hmac('sha256', "{$t}.{$payload}", $secret);
        }

        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], $headers, $payload);
    }

    private function balance(Transaction $tx): string
    {
        return app(LedgerService::class)->balanceFor($tx->wallet_id, $tx->asset_id);
    }

    public function test_valid_succeeded_event_completes_the_deposit_and_credits_exactly_the_amount(): void
    {
        $tx = $this->pendingDeposit();

        $this->deliver('payment_intent.succeeded', $this->intent($tx))->assertOk()->assertJson(['result' => 'completed']);

        $this->assertSame('completed', $tx->fresh()->status);
        $this->assertSame('75.00000000', $this->balance($tx));
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction.completed', 'actor_type' => 'system', 'subject_id' => $tx->id]);
    }

    public function test_forged_signature_is_rejected_and_nothing_changes(): void
    {
        $tx = $this->pendingDeposit();

        $this->deliver('payment_intent.succeeded', $this->intent($tx), 'whsec_attacker')->assertStatus(400);

        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertSame('0.00000000', $this->balance($tx));
    }

    public function test_missing_signature_header_is_rejected(): void
    {
        $tx = $this->pendingDeposit();

        $this->deliver('payment_intent.succeeded', $this->intent($tx), null)->assertStatus(400);
        $this->assertSame('pending', $tx->fresh()->status);
    }

    public function test_stale_timestamp_is_rejected_even_with_a_valid_hmac(): void
    {
        $tx = $this->pendingDeposit();

        $this->deliver('payment_intent.succeeded', $this->intent($tx), self::SECRET, time() - 3600)->assertStatus(400);
        $this->assertSame('pending', $tx->fresh()->status);
    }

    public function test_unconfigured_webhook_secret_fails_closed(): void
    {
        config(['services.stripe.webhook_secret' => null]);
        $tx = $this->pendingDeposit();

        $this->deliver('payment_intent.succeeded', $this->intent($tx))->assertStatus(500);
        $this->assertSame('pending', $tx->fresh()->status);
    }

    public function test_malformed_signed_payloads_are_rejected(): void
    {
        $this->pendingDeposit();

        $this->deliverRaw('not json at all')->assertStatus(400);
        $this->deliverRaw(json_encode(['type' => 'payment_intent.succeeded']))->assertStatus(400);
        $this->deliverRaw(json_encode(['data' => ['object' => ['id' => 'pi_test_1']]]))->assertStatus(400);
        $this->deliverRaw(json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['status' => 'succeeded']]]))->assertStatus(400);
    }

    public function test_amount_mismatch_does_not_credit_and_is_audited(): void
    {
        $tx = $this->pendingDeposit();

        $this->deliver('payment_intent.succeeded', $this->intent($tx, ['amount' => 100, 'amount_received' => 100]))
            ->assertOk()->assertJson(['result' => 'rejected:amount_mismatch']);

        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertSame('0.00000000', $this->balance($tx));
        $this->assertSame('amount_mismatch', AuditLog::where('action', 'deposit.webhook_rejected')->first()->after['reason']);
    }

    public function test_overpayment_is_not_credited_either(): void
    {
        $tx = $this->pendingDeposit();

        $this->deliver('payment_intent.succeeded', $this->intent($tx, ['amount_received' => 999900]))
            ->assertOk()->assertJson(['result' => 'rejected:amount_mismatch']);
        $this->assertSame('pending', $tx->fresh()->status);
    }

    public function test_currency_status_and_metadata_mismatches_are_rejected(): void
    {
        $tx = $this->pendingDeposit();

        $this->deliver('payment_intent.succeeded', $this->intent($tx, ['currency' => 'eur']))->assertJson(['result' => 'rejected:currency_mismatch']);
        $this->deliver('payment_intent.succeeded', $this->intent($tx, ['status' => 'requires_payment_method']))->assertJson(['result' => 'rejected:status_not_succeeded']);
        $this->deliver('payment_intent.succeeded', $this->intent($tx, ['metadata' => ['transaction_id' => (string) Str::uuid()]]))->assertJson(['result' => 'rejected:metadata_mismatch']);

        $this->assertSame('pending', $tx->fresh()->status);
    }

    public function test_duplicate_delivery_credits_only_once(): void
    {
        $tx = $this->pendingDeposit();

        $this->deliver('payment_intent.succeeded', $this->intent($tx))->assertJson(['result' => 'completed']);
        $this->deliver('payment_intent.succeeded', $this->intent($tx))->assertJson(['result' => 'duplicate']);

        $this->assertSame('75.00000000', $this->balance($tx));
        $this->assertSame(1, AuditLog::where('action', 'transaction.completed')->count());
    }

    public function test_stale_model_cannot_reprocess_a_deposit_that_another_worker_completed(): void
    {
        // Models what a concurrent worker sees: a pending copy loaded BEFORE the webhook finished.
        // The lifecycle service re-reads under lock, so the stale copy cannot double-apply.
        $tx    = $this->pendingDeposit();
        $stale = Transaction::find($tx->id);

        $this->deliver('payment_intent.succeeded', $this->intent($tx))->assertOk();

        $this->expectException(\App\Exceptions\InvalidTransactionTransition::class);
        try {
            app(TransactionLifecycleService::class)->complete($stale, TransactionLifecycleService::ACTOR_SYSTEM);
        } finally {
            $this->assertSame('75.00000000', $this->balance($tx));
        }
    }

    public function test_one_provider_payment_cannot_back_two_deposits(): void
    {
        $tx = $this->pendingDeposit('pi_dup');

        $this->expectException(QueryException::class);
        Transaction::create([
            'wallet_id' => $tx->wallet_id, 'asset_id' => $tx->asset_id, 'type' => 'deposit', 'amount' => '5.00',
            'status' => 'pending', 'reference' => 'x', 'external_id' => 'pi_dup',
        ]);
    }

    public function test_failed_payment_cancels_and_a_late_success_cannot_resurrect_it(): void
    {
        $tx = $this->pendingDeposit();

        $this->deliver('payment_intent.payment_failed', $this->intent($tx, ['status' => 'requires_payment_method']))->assertJson(['result' => 'cancelled']);
        $this->assertSame('cancelled', $tx->fresh()->status);

        $this->deliver('payment_intent.succeeded', $this->intent($tx))->assertJson(['result' => 'duplicate']);
        $this->assertSame('cancelled', $tx->fresh()->status);
        $this->assertSame('0.00000000', $this->balance($tx));
    }

    public function test_unknown_event_type_and_unknown_intent_are_acknowledged_without_effect(): void
    {
        $tx = $this->pendingDeposit();

        $this->deliver('charge.refunded', $this->intent($tx))->assertOk();
        $this->deliver('payment_intent.succeeded', $this->intent($tx, ['id' => 'pi_nope']))->assertOk()->assertJson(['result' => 'unknown_intent']);

        $this->assertSame('pending', $tx->fresh()->status);
    }

    public function test_an_intent_id_that_belongs_to_a_non_deposit_is_never_completed(): void
    {
        $tx = $this->pendingDeposit();
        $tx->update(['type' => 'withdrawal']);

        $this->deliver('payment_intent.succeeded', $this->intent($tx))->assertJson(['result' => 'unknown_intent']);
        $this->assertSame('pending', $tx->fresh()->status);
    }
}
