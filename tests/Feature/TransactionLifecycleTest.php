<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransactionTransition;
use App\Models\Transaction;
use App\Services\FundsService;
use App\Services\TransactionLifecycleService as Lifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class TransactionLifecycleTest extends TestCase
{
    use RefreshDatabase, BuildsLedger;

    private function lifecycle(): Lifecycle
    {
        return app(Lifecycle::class);
    }

    private function pendingTransfer(string $amount = '10', $asset = null): array
    {
        $asset ??= $this->asset('btc', '60000');
        $from  = $this->wallet();
        $to    = $this->wallet();
        $this->fund($from, $asset, '50');

        $out = app(FundsService::class)->createTransfer($from, ['asset_id' => $asset->id, 'recipient_id' => $to->id, 'amount' => $amount]);

        return [$asset, $from, $to, $out];
    }

    private function pendingSwap(string $feeRate = '1'): array
    {
        $usdt = $this->asset('usdt', '1', 8, ['swap_charge_rate' => $feeRate]);
        $eth  = $this->asset('eth', '3000');
        $w    = $this->wallet();
        $this->fund($w, $usdt, '2020');

        $out = app(FundsService::class)->initiateSwap($w, ['from_asset_id' => $usdt->id, 'to_asset_id' => $eth->id, 'amount' => '2000']);

        return [$usdt, $eth, $w, $out];
    }

    public function test_completing_a_transfer_moves_both_legs_together(): void
    {
        [$asset, $from, $to, $out] = $this->pendingTransfer('10');

        $legs = $this->lifecycle()->complete($out, Lifecycle::ACTOR_ADMIN, 'admin-1');

        $this->assertCount(2, $legs);
        $this->assertSame(['completed'], Transaction::whereIn('id', $legs->pluck('id'))->pluck('status')->unique()->all());
        $this->assertSame('40.00000000', $this->balance($from, $asset));
        $this->assertSame('10.00000000', $this->balance($to, $asset));
    }

    public function test_cancelling_a_transfer_releases_the_reserved_funds(): void
    {
        [$asset, $from, $to, $out] = $this->pendingTransfer('10');
        $this->assertSame('40.00000000', $this->balance($from, $asset)); // reserved while pending

        $this->lifecycle()->cancel($out, Lifecycle::ACTOR_ADMIN, 'admin-1');

        $this->assertSame('50.00000000', $this->balance($from, $asset));
        $this->assertSame('0.00000000', $this->balance($to, $asset));
    }

    public function test_swap_reserves_amount_plus_fee_then_reconciles_on_completion(): void
    {
        [$usdt, $eth, $w, $out] = $this->pendingSwap('1');

        // 2000 swapped + 20 fee reserved while pending; nothing credited yet.
        $this->assertSame('0.00000000', $this->balance($w, $usdt));
        $this->assertSame('0.00000000', $this->balance($w, $eth));

        $this->lifecycle()->complete($out, Lifecycle::ACTOR_ADMIN);

        $this->assertSame('0.00000000', $this->balance($w, $usdt));                // 2020 - 2000 - 20
        $this->assertSame('0.66666667', $this->balance($w, $eth));                 // 2000/3000, 8dp
        $this->assertSame(3, Transaction::where('wallet_id', $w->id)->where('type', '!=', 'deposit')->where('status', 'completed')->count());
    }

    public function test_cancelling_a_swap_refunds_the_amount_and_the_fee_leg(): void
    {
        [$usdt, $eth, $w, $out] = $this->pendingSwap('1');

        $this->lifecycle()->cancel($out, Lifecycle::ACTOR_ADMIN);

        $this->assertSame('2020.00000000', $this->balance($w, $usdt));
        $this->assertSame('0.00000000', $this->balance($w, $eth));
        $this->assertSame(0, Transaction::where('wallet_id', $w->id)->where('type', '!=', 'deposit')->where('status', 'pending')->count());
    }

    public function test_acting_on_the_incoming_or_fee_leg_moves_the_whole_swap(): void
    {
        [$usdt, $eth, $w] = $this->pendingSwap('1');
        $fee = Transaction::where('wallet_id', $w->id)->where('meta->swap_charge', true)->first();

        $this->lifecycle()->cancel($fee, Lifecycle::ACTOR_ADMIN);

        $this->assertSame('2020.00000000', $this->balance($w, $usdt));
    }

    public function test_terminal_states_cannot_be_processed_again(): void
    {
        [$asset, $from, , $out] = $this->pendingTransfer('10');
        $this->lifecycle()->complete($out, Lifecycle::ACTOR_ADMIN);

        foreach (['complete', 'cancel'] as $method) {
            try {
                $this->lifecycle()->{$method}($out->fresh(), Lifecycle::ACTOR_ADMIN);
                $this->fail("{$method} on a completed transfer must throw");
            } catch (InvalidTransactionTransition) {
                $this->assertSame('40.00000000', $this->balance($from, $asset));
            }
        }

        [$a2, $f2, , $out2] = $this->pendingTransfer('5', $asset);
        $this->lifecycle()->cancel($out2, Lifecycle::ACTOR_ADMIN);
        $this->expectException(InvalidTransactionTransition::class);
        $this->lifecycle()->complete($out2->fresh(), Lifecycle::ACTOR_ADMIN);
    }

    public function test_contradictory_legs_block_the_transition_and_change_nothing(): void
    {
        [$usdt, , $w, $out] = $this->pendingSwap('1');
        // Corrupt the group: one leg already completed while the rest are pending.
        Transaction::where('wallet_id', $w->id)->where('meta->direction', 'incoming')->update(['status' => 'completed']);

        try {
            $this->lifecycle()->cancel($out, Lifecycle::ACTOR_ADMIN);
            $this->fail('Expected a refusal');
        } catch (InvalidTransactionTransition $e) {
            $this->assertStringContainsString('inconsistent', $e->getMessage());
        }

        $this->assertSame(2, Transaction::where('wallet_id', $w->id)->where('status', 'pending')->count()); // outgoing + fee untouched
    }

    public function test_deposits_are_only_moved_by_the_system_actor(): void
    {
        $usd = $this->usd();
        $w   = $this->wallet();
        $dep = Transaction::create(['wallet_id' => $w->id, 'asset_id' => $usd->id, 'type' => 'deposit', 'amount' => '10', 'status' => 'pending', 'reference' => 'r', 'external_id' => 'pi_x']);

        foreach ([Lifecycle::ACTOR_ADMIN, Lifecycle::ACTOR_USER] as $actor) {
            foreach (['complete', 'cancel'] as $method) {
                try {
                    $this->lifecycle()->{$method}($dep, $actor);
                    $this->fail("{$actor} must not {$method} a deposit");
                } catch (InvalidTransactionTransition) {
                }
            }
        }

        $this->assertSame('pending', $dep->fresh()->status);
        $this->lifecycle()->complete($dep, Lifecycle::ACTOR_SYSTEM);
        $this->assertSame('10.00000000', $this->balance($w, $usd));
    }

    public function test_users_can_cancel_but_never_complete(): void
    {
        [$asset, $from, , $out] = $this->pendingTransfer('10');

        try {
            $this->lifecycle()->complete($out, Lifecycle::ACTOR_USER);
            $this->fail('users must not complete');
        } catch (InvalidTransactionTransition) {
        }

        $this->lifecycle()->cancel($out, Lifecycle::ACTOR_USER, $from->id);
        $this->assertSame('50.00000000', $this->balance($from, $asset));
    }

    public function test_every_transition_is_audited_with_actor_and_legs(): void
    {
        [, , , $out] = $this->pendingTransfer('10');

        $this->lifecycle()->complete($out, Lifecycle::ACTOR_ADMIN, 'admin-42');

        $log = \App\Models\AuditLog::where('action', 'transaction.completed')->firstOrFail();
        $this->assertSame('admin', $log->actor_type);
        $this->assertSame('admin-42', $log->actor_id);
        $this->assertCount(2, $log->after['legs']);
    }

    // ── HTTP: user cancellation API ───────────────────────────────────────────

    public function test_user_cancel_endpoint_enforces_ownership_and_deposit_rule(): void
    {
        [$asset, $from, , $out] = $this->pendingTransfer('10');
        $mallory = $this->wallet();

        Sanctum::actingAs($mallory);
        $this->patchJson("/api/v1/transactions/{$out->id}/cancel", ['status' => 'cancelled'])->assertStatus(403);
        $this->assertSame('pending', $out->fresh()->status);

        Sanctum::actingAs($from);
        $this->patchJson("/api/v1/transactions/{$out->id}/cancel", ['status' => 'cancelled'])->assertOk();
        $this->assertSame('cancelled', $out->fresh()->status);
        $this->patchJson("/api/v1/transactions/{$out->id}/cancel", ['status' => 'cancelled'])->assertStatus(422); // replay

        $dep = Transaction::create(['wallet_id' => $from->id, 'asset_id' => $this->usd()->id, 'type' => 'deposit', 'amount' => '5', 'status' => 'pending', 'reference' => 'r', 'external_id' => 'pi_y']);
        $this->patchJson("/api/v1/transactions/{$dep->id}/cancel", ['status' => 'cancelled'])->assertStatus(422);
        $this->assertSame('pending', $dep->fresh()->status);
    }

    // ── HTTP: admin actions ───────────────────────────────────────────────────

    public function test_admin_actions_are_hidden_from_guests_and_non_admins_and_work_for_admins(): void
    {
        [$asset, $from, $to, $out] = $this->pendingTransfer('10');
        $url = route('admin.transactions.complete', $out);

        $this->post($url)->assertRedirect(route('admin.login'));                          // guest: bounced to login
        $this->assertSame('pending', $out->fresh()->status);

        $this->actingAs($this->user(['admin' => false]), 'admin')->post($url)->assertNotFound(); // regular user

        $admin = $this->user(['admin' => true]);
        $this->actingAs($admin, 'admin')->post($url)->assertRedirect();
        $this->assertSame('completed', $out->fresh()->status);
        $this->assertSame('10.00000000', $this->balance($to, $asset));

        $this->actingAs($admin, 'admin')->post($url)->assertSessionHas('error');             // replay refused
        $this->assertSame('10.00000000', $this->balance($to, $asset));
    }

    public function test_admin_cannot_complete_a_stripe_deposit_through_the_panel(): void
    {
        $dep = Transaction::create(['wallet_id' => $this->wallet()->id, 'asset_id' => $this->usd()->id, 'type' => 'deposit', 'amount' => '99', 'status' => 'pending', 'reference' => 'r', 'external_id' => 'pi_z']);
        $admin = $this->user(['admin' => true]);

        $this->actingAs($admin, 'admin')->post(route('admin.transactions.complete', $dep))->assertSessionHas('error');
        $this->actingAs($admin, 'admin')->post(route('admin.transactions.cancel', $dep))->assertSessionHas('error');

        $this->assertSame('pending', $dep->fresh()->status);
    }
}
