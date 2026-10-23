<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Services\FundsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class FundsBehaviorTest extends TestCase
{
    use RefreshDatabase, BuildsLedger;

    private function funds(): FundsService
    {
        return app(FundsService::class);
    }

    // ── Withdrawals ───────────────────────────────────────────────────────────

    public function test_withdrawal_reserves_funds_and_allows_partial_amounts(): void
    {
        $btc = $this->asset('btc', '60000');
        $w   = $this->wallet();
        $this->fund($w, $btc, '1');
        $data = ['asset_id' => $btc->id, 'amount' => '0.25', 'sub_method_id' => null, 'reference' => 'bank-note'];

        $this->funds()->withdrawalPreflight($w, $data);          // partial withdrawal is valid (no "full balance only" rule)
        $tx = $this->funds()->createWithdrawal($w, $data);

        $this->assertSame('pending', $tx->status);
        $this->assertSame('0.75000000', $this->balance($w, $btc));
    }

    public function test_withdrawal_rejects_insufficient_balance_and_a_second_pending_one(): void
    {
        $btc = $this->asset('btc', '60000');
        $w   = $this->wallet();
        $this->fund($w, $btc, '1');

        try {
            $this->funds()->withdrawalPreflight($w, ['asset_id' => $btc->id, 'amount' => '1.00000001']);
            $this->fail('insufficient balance accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $data = ['asset_id' => $btc->id, 'amount' => '0.1', 'sub_method_id' => null, 'reference' => 'r'];
        $this->funds()->createWithdrawal($w, $data);

        $this->expectException(ValidationException::class);
        $this->funds()->createWithdrawal($w, $data);
    }

    // ── Transfers ─────────────────────────────────────────────────────────────

    public function test_transfer_rejects_self_unknown_recipient_and_overdraft(): void
    {
        $btc = $this->asset('btc', '60000');
        $a = $this->wallet();
        $b = $this->wallet();
        $this->fund($a, $btc, '1');

        $cases = [
            ['recipient_id' => $a->id, 'amount' => '0.1'],
            ['recipient_id' => (string) \Illuminate\Support\Str::uuid(), 'amount' => '0.1'],
            ['recipient_id' => $b->id, 'amount' => '1.5'],
        ];

        foreach ($cases as $case) {
            try {
                $this->funds()->createTransfer($a, ['asset_id' => $btc->id] + $case);
                $this->fail('invalid transfer accepted');
            } catch (ValidationException) {
            }
        }

        $this->assertSame('1.00000000', $this->balance($a, $btc));
        $this->assertSame(0, Transaction::where('type', 'transfer')->count());
    }

    // ── Swaps ─────────────────────────────────────────────────────────────────

    public function test_swap_needs_balance_for_amount_plus_fee(): void
    {
        $usdt = $this->asset('usdt', '1', 8, ['swap_charge_rate' => '1']);
        $eth  = $this->asset('eth', '3000');
        $w    = $this->wallet();
        $this->fund($w, $usdt, '2000');                       // covers 2000 but not 2000 + 20 fee

        $this->expectException(ValidationException::class);
        $this->funds()->initiateSwap($w, ['from_asset_id' => $usdt->id, 'to_asset_id' => $eth->id, 'amount' => '2000']);
    }

    public function test_swap_records_three_legs_with_the_expected_numbers(): void
    {
        $usdt = $this->asset('usdt', '1', 8, ['swap_charge_rate' => '1']);
        $eth  = $this->asset('eth', '3000');
        $w    = $this->wallet();
        $this->fund($w, $usdt, '2020');

        $out = $this->funds()->initiateSwap($w, ['from_asset_id' => $usdt->id, 'to_asset_id' => $eth->id, 'amount' => '2000']);
        $legs = Transaction::where('meta->swap_id', $out->meta['swap_id'])->get();

        $this->assertCount(3, $legs);
        $this->assertSame('2000.00000000', $legs->firstWhere('meta.direction', 'outgoing')->amount);
        $this->assertSame('0.66666667', $legs->firstWhere('meta.direction', 'incoming')->amount);
        $this->assertSame('20.00000000', $legs->firstWhere('meta.swap_charge', true)->amount);
    }

    // ── Sells ─────────────────────────────────────────────────────────────────

    public function test_sell_locks_the_asset_and_records_gross_fee_net_in_cents(): void
    {
        $btc = $this->asset('btc', '60000', 8, ['sell_charge_rate' => '2']);
        $w   = $this->wallet();
        $this->fund($w, $btc, '1');

        $tx = $this->funds()->initiateSell($w, ['asset_id' => $btc->id, 'amount' => '0.12345678']);

        $this->assertSame('pending', $tx->status);
        $this->assertSame('0.87654322', $this->balance($w, $btc));
        // 0.12345678 × 60000 = 7407.4068 → 7407.41; 2% fee = 148.1482 → 148.15; net 7259.26 (all to the cent)
        $this->assertSame('7407.41000000', $tx->meta['gross_fiat']);
        $this->assertSame('148.15000000', $tx->meta['charge_fiat']);
        $this->assertSame('7259.26000000', $tx->meta['net_fiat']);
    }

    public function test_sell_rejects_more_than_the_balance_and_unpriced_assets(): void
    {
        $btc = $this->asset('btc', '60000');
        $xyz = $this->asset('xyz');
        $w   = $this->wallet();
        $this->fund($w, $btc, '1');
        $this->fund($w, $xyz, '5');

        foreach ([[$btc, '1.5'], [$xyz, '1']] as [$asset, $amount]) {
            try {
                $this->funds()->initiateSell($w, ['asset_id' => $asset->id, 'amount' => $amount]);
                $this->fail('invalid sell accepted');
            } catch (ValidationException) {
            }
        }
        $this->assertSame(0, Transaction::where('type', 'sell')->count());
    }

    // ── Deposits / buys: provider reference and charge reconciliation ─────────

    public function test_deposit_stores_the_provider_id_in_the_unique_column(): void
    {
        $this->usd();
        $w = $this->wallet();
        \Illuminate\Support\Facades\Http::fake(['api.stripe.com/*' => \Illuminate\Support\Facades\Http::response(['id' => 'pi_abc', 'client_secret' => 's'], 200)]);

        $r = $this->funds()->initiateDeposit($w, ['usd_amount' => '12.34']);

        $this->assertSame('pi_abc', $r['transaction']->fresh()->external_id);
        $this->assertSame('12.34000000', $r['transaction']->fresh()->amount);
    }

    public function test_buy_charge_reconciles_usd_debit_to_the_cent(): void
    {
        $usd = $this->usd();
        $btc = $this->asset('btc', '60000', 8, ['buy_charge_rate' => '2']);
        $w   = $this->wallet();
        $this->fund($w, $usd, '100.00');

        $this->funds()->initiateBuy($w, ['asset_id' => $btc->id, 'usd_amount' => '50.00']);

        $legs = Transaction::where('wallet_id', $w->id)->where('type', '!=', 'deposit')->get();
        $this->assertSame('50.00000000', $this->balance($w, $usd));
        $this->assertSame('49.00000000', $legs->firstWhere('meta.direction', 'outgoing')->amount);        // net swapped
        $this->assertSame('1.00000000', $legs->firstWhere('meta.swap_charge', true)->amount);             // fee
        $this->assertSame('0.00081667', $this->balance($w, $btc));                                          // 49 / 60000
        $this->assertSame(['completed'], $legs->pluck('status')->unique()->values()->all());
    }

    public function test_buy_rejects_more_than_two_usd_decimals(): void
    {
        $usd = $this->usd();
        $btc = $this->asset('btc', '60000');
        $w   = $this->wallet();
        $this->fund($w, $usd, '100');

        $this->expectException(ValidationException::class);
        $this->funds()->initiateBuy($w, ['asset_id' => $btc->id, 'usd_amount' => '10.001']);
    }
}
