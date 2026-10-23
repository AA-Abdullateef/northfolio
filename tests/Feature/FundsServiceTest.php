<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransferInitiated;
use App\Http\Controllers\API\V1\AccountController;
use App\Models\Asset;
use App\Models\Method;
use App\Models\SubMethod;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\FundsService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class FundsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_creates_paired_legs_with_shared_transfer_id(): void
    {
        Event::fake([TransferInitiated::class]);

        $asset = $this->asset();
        $sender = $this->wallet('sender');
        $recipient = $this->wallet('recipient');

        $this->completedDeposit($sender, $asset, 10);

        $transfer = app(FundsService::class)->createTransfer($sender, [
            'asset_id' => $asset->id,
            'amount' => 1,
            'recipient_id' => $recipient->id,
        ]);

        $incoming = Transaction::query()
            ->where('wallet_id', $recipient->id)
            ->where('type', TransactionType::Transfer->value)
            ->firstOrFail();

        $this->assertSame('outgoing', $transfer->meta['direction']);
        $this->assertSame('incoming', $incoming->meta['direction']);
        $this->assertSame($transfer->meta['transfer_id'], $incoming->meta['transfer_id']);
        $this->assertSame($incoming->id, $transfer->relatedPendingTransferLeg()?->id);
        $this->assertSame($transfer->id, $incoming->relatedPendingTransferLeg()?->id);
    }

    public function test_duplicate_transfers_are_paired_by_transfer_id_not_amount_heuristics(): void
    {
        Event::fake([TransferInitiated::class]);

        $asset = $this->asset();
        $sender = $this->wallet('sender');
        $recipient = $this->wallet('recipient');

        $this->completedDeposit($sender, $asset, 10);

        $first = app(FundsService::class)->createTransfer($sender, [
            'asset_id' => $asset->id,
            'amount' => 1,
            'recipient_id' => $recipient->id,
        ]);

        $second = app(FundsService::class)->createTransfer($sender, [
            'asset_id' => $asset->id,
            'amount' => 1,
            'recipient_id' => $recipient->id,
        ]);

        $firstIncoming = Transaction::query()
            ->where('wallet_id', $recipient->id)
            ->where('meta->transfer_id', $first->meta['transfer_id'])
            ->firstOrFail();

        $secondIncoming = Transaction::query()
            ->where('wallet_id', $recipient->id)
            ->where('meta->transfer_id', $second->meta['transfer_id'])
            ->firstOrFail();

        $this->assertNotSame($first->meta['transfer_id'], $second->meta['transfer_id']);
        $this->assertSame($firstIncoming->id, $first->relatedPendingTransferLeg()?->id);
        $this->assertSame($secondIncoming->id, $second->relatedPendingTransferLeg()?->id);
    }

    public function test_pending_transfer_debits_sender_until_paired_completion(): void
    {
        Event::fake([TransferInitiated::class]);

        $asset = $this->asset();
        $sender = $this->wallet('sender');
        $recipient = $this->wallet('recipient');
        $ledger = app(LedgerService::class);

        $this->completedDeposit($sender, $asset, 10);

        $outgoing = app(FundsService::class)->createTransfer($sender, [
            'asset_id' => $asset->id,
            'amount' => 2,
            'recipient_id' => $recipient->id,
        ]);

        $this->assertSame('8.00000000', $ledger->balanceFor($sender->id, $asset->id));
        $this->assertSame('0.00000000', $ledger->balanceFor($recipient->id, $asset->id));

        $incoming = $outgoing->relatedPendingTransferLeg();
        $outgoing->update(['status' => TransactionStatus::Completed->value]);
        $incoming?->update(['status' => TransactionStatus::Completed->value]);

        $this->assertSame('8.00000000', $ledger->balanceFor($sender->id, $asset->id));
        $this->assertSame('2.00000000', $ledger->balanceFor($recipient->id, $asset->id));
    }

    public function test_total_usd_balance_uses_positive_prices_and_reports_unpriced_assets(): void
    {
        $wallet = $this->wallet('holder');
        $usd = $this->asset('usd', 1);
        $btc = $this->asset('btc', 50000);
        $unpriced = $this->asset('mystery');

        $this->completedDeposit($wallet, $usd, 100);
        $this->completedDeposit($wallet, $btc, 0.5);
        $this->completedDeposit($wallet, $unpriced, 20);

        $total = app(LedgerService::class)->totalUsdBalanceFor($wallet->id);

        $this->assertSame('25100.00000000', $total['total_usd']);
        $this->assertSame('25000.00000000', collect($total['assets'])->firstWhere('asset_id', $btc->id)['value_usd']);
        $this->assertSame(1, $total['unpriced_asset_count']);
    }

    public function test_dashboard_returns_legacy_balance_key_as_total_usd_balance(): void
    {
        $wallet = $this->wallet('dashboard');
        $usd = $this->asset('usd', 1);
        $btc = $this->asset('btc', 50000);
        $subMethod = $this->subMethod();

        $this->completedDeposit($wallet, $usd, 100, $subMethod);
        $this->completedDeposit($wallet, $btc, 0.5);

        $request = Request::create('/api/v1/dashboard', 'GET');
        $request->setUserResolver(fn () => $wallet);

        $response = app(AccountController::class)->dashboard($request);
        $payload = $response->getData(true);

        $this->assertEquals(25100.0, $payload['data']['balance']);
        $this->assertEquals(25100.0, $payload['data']['portfolio_usd']['total_usd']);
        $this->assertArrayHasKey('balances', $payload['data']);
        $this->assertArrayHasKey('asset_balances', $payload['data']);
        $depositWithSubMethod = collect($payload['data']['recent'])
            ->firstWhere('sub_method.id', $subMethod->id);

        $this->assertNotNull($depositWithSubMethod);
    }

    public function test_buy_debits_usd_and_credits_asset_instantly(): void
    {
        $usd = $this->asset('usd', 1.0);
        $btc = $this->asset('btc', 60000.0);
        $btc->forceFill(['buy_charge_rate' => 2])->save();
        $wallet = $this->wallet('buy');
        $this->completedDeposit($wallet, $usd, 1000);

        $tx = app(FundsService::class)->initiateBuy($wallet, ['asset_id' => $btc->id, 'usd_amount' => 50]);

        $ledger = app(LedgerService::class);
        $this->assertSame(TransactionStatus::Completed->value, $tx->status);
        $this->assertEqualsWithDelta(950.0, $ledger->balanceFor($wallet->id, $usd->id), 0.00001);
        $this->assertEqualsWithDelta(0.00081667, $ledger->balanceFor($wallet->id, $btc->id), 0.00001);
    }

    public function test_buy_rejects_insufficient_usd(): void
    {
        $usd = $this->asset('usd', 1.0);
        $btc = $this->asset('btc', 60000.0);
        $wallet = $this->wallet('poor');
        $this->completedDeposit($wallet, $usd, 10);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(FundsService::class)->initiateBuy($wallet, ['asset_id' => $btc->id, 'usd_amount' => 50]);
    }

    public function test_buy_rejects_unpriced_asset_and_creates_nothing(): void
    {
        $usd = $this->asset('usd', 1.0);
        $xyz = $this->asset('xyz');
        $wallet = $this->wallet('unpriced');
        $this->completedDeposit($wallet, $usd, 100);

        try {
            app(FundsService::class)->initiateBuy($wallet, ['asset_id' => $xyz->id, 'usd_amount' => 10]);
            $this->fail('Expected validation failure');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertSame(1, Transaction::count());
        }
    }

    public function test_buy_charge_comes_out_of_the_usd_spent(): void
    {
        $usd = $this->asset('usd', 1.0);
        $eth = $this->asset('eth', 2000.0);
        $eth->forceFill(['buy_charge_rate' => 10])->save();
        $wallet = $this->wallet('charge');
        $this->completedDeposit($wallet, $usd, 100);

        app(FundsService::class)->initiateBuy($wallet, ['asset_id' => $eth->id, 'usd_amount' => 100]);

        $ledger = app(LedgerService::class);
        $this->assertEqualsWithDelta(0.0, $ledger->balanceFor($wallet->id, $usd->id), 0.00001);
        $this->assertEqualsWithDelta(0.045, $ledger->balanceFor($wallet->id, $eth->id), 0.000001);
    }

    public function test_deposit_creates_pending_transaction_with_payment_intent(): void
    {
        $this->asset('usd', 1.0);
        $wallet = $this->wallet('dep');
        \Illuminate\Support\Facades\Http::fake([
            'api.stripe.com/*' => \Illuminate\Support\Facades\Http::response(['id' => 'pi_123', 'client_secret' => 'pi_123_secret'], 200),
        ]);

        $result = app(FundsService::class)->initiateDeposit($wallet, ['usd_amount' => 25.5]);

        $this->assertSame(TransactionStatus::Pending->value, $result['transaction']->status);
        $this->assertSame('pi_123', $result['transaction']->meta['stripe_payment_intent_id']);
        $this->assertSame('pi_123_secret', $result['client_secret']);
        $this->assertEquals(0.0, app(LedgerService::class)->balanceFor($wallet->id, $result['transaction']->asset_id));
    }

    private function asset(string $name = 'btc', ?float $price = null): Asset
    {
        return Asset::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'label' => strtoupper($name),
            'active' => true,
            'price_source' => $price === null ? null : 'manual',
            'current_price' => $price,
        ]);
    }

    private function wallet(string $suffix): Wallet
    {
        return Wallet::forceCreate([
            'id' => (string) Str::uuid(),
            'mnemonic_hash' => hash('sha256', "wallet-{$suffix}"),
            'fingerprint' => hash('sha256', "fingerprint-{$suffix}"),
            'public_id' => hash('sha256', "public-{$suffix}"),
        ]);
    }

    private function subMethod(): SubMethod
    {
        $method = Method::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Crypto',
        ]);

        return SubMethod::forceCreate([
            'id' => (string) Str::uuid(),
            'method_id' => $method->id,
            'name' => 'Trust Wallet',
            'wallet_address' => 'TXn9pQq',
            'network' => 'TRC20',
            'is_active' => true,
        ]);
    }

    private function completedDeposit(Wallet $wallet, Asset $asset, float $amount, ?SubMethod $subMethod = null): Transaction
    {
        return Transaction::create([
            'wallet_id' => $wallet->id,
            'asset_id' => $asset->id,
            'sub_method_id' => $subMethod?->id,
            'type' => TransactionType::Deposit->value,
            'amount' => $amount,
            'status' => TransactionStatus::Completed->value,
            'reference' => (string) str()->uuid(),
        ]);
    }
}
