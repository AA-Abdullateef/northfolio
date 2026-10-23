<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Services\FundsService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class PrecisionTest extends TestCase
{
    use RefreshDatabase, BuildsLedger;

    public function test_eight_decimals_survive_create_persist_ledger_and_api(): void
    {
        $btc = $this->asset('btc', '60000');
        $w   = $this->wallet();
        $tx  = $this->fund($w, $btc, '0.12345678');

        // persistence (re-read from the database, not the in-memory model)
        $this->assertSame('0.12345678', Transaction::find($tx->id)->amount);
        $this->assertEquals(0.12345678, (float) DB::table('transactions')->where('id', $tx->id)->value('amount'));

        // ledger
        $this->assertSame('0.12345678', $this->balance($w, $btc));

        // API serialisation: exact string, not a float
        Sanctum::actingAs($w);
        $row = collect($this->getJson('/api/v1/transactions')->assertOk()->json('data'))->firstWhere('id', $tx->id);
        $this->assertSame('0.12345678', $row['amount']);
    }

    public function test_decimal_sums_are_exact(): void
    {
        $btc = $this->asset('btc', '60000');
        $w   = $this->wallet();
        foreach (['0.1', '0.2', '0.00000001', '0.00000002'] as $a) {
            $this->fund($w, $btc, $a);
        }

        $this->assertSame('0.30000003', $this->balance($w, $btc));   // float arithmetic would give …04
    }

    public function test_large_balances_keep_all_digits(): void
    {
        $btc = $this->asset('btc', '60000');
        $w   = $this->wallet();
        $this->fund($w, $btc, '1234567.12345678');
        $this->fund($w, $btc, '0.00000001');

        $this->assertSame('1234567.12345679', $this->balance($w, $btc));
    }

    public function test_input_precision_is_enforced_per_asset(): void
    {
        $usd = $this->usd();                      // 2 decimals
        $btc = $this->asset('btc', '60000');      // 8 decimals
        $w   = $this->wallet();
        $this->fund($w, $usd, '1000');
        $this->fund($w, $btc, '1');
        $funds = app(FundsService::class);

        $funds->withdrawalPreflight($w, ['asset_id' => $btc->id, 'amount' => '0.00000001']);  // 8dp ok for BTC

        foreach ([[$btc, '0.000000001'], [$usd, '10.005']] as [$asset, $amount]) {
            try {
                $funds->withdrawalPreflight($w, ['asset_id' => $asset->id, 'amount' => $amount]);
                $this->fail("{$asset->name} accepted {$amount}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('decimal places', $e->errors()['amount'][0]);
            }
        }
    }

    public function test_swap_conversion_rounds_to_the_target_assets_precision(): void
    {
        $eth = $this->asset('eth', '3000');
        $usd = $this->usd();
        $w   = $this->wallet();
        $this->fund($w, $eth, '1');

        // 0.33333333 ETH × 3000 = 999.99999 USD → USD has 2 decimals → 1000.00
        $out = app(FundsService::class)->initiateSwap($w, ['from_asset_id' => $eth->id, 'to_asset_id' => $usd->id, 'amount' => '0.33333333']);

        $in = Transaction::where('meta->swap_id', $out->meta['swap_id'])->where('meta->direction', 'incoming')->first();
        $this->assertSame('1000.00000000', $in->amount);
    }

    public function test_swap_fee_is_rounded_to_the_source_assets_precision_and_is_a_whole_ledger_row(): void
    {
        $usd  = $this->usd();                                  // 2 decimals
        $btc  = $this->asset('btc', '60000');
        $usd->forceFill(['swap_charge_rate' => '1.5'])->save();
        $w = $this->wallet();
        $this->fund($w, $usd, '100');

        $out = app(FundsService::class)->initiateSwap($w, ['from_asset_id' => $usd->id, 'to_asset_id' => $btc->id, 'amount' => '33.33']);

        $fee = Transaction::where('meta->swap_id', $out->meta['swap_id'])->where('meta->swap_charge', true)->first();
        $this->assertSame('0.50000000', $fee->amount);          // 1.5% of 33.33 = 0.49995 → 0.50 (cents)
        $this->assertSame('66.17000000', $this->balance($w, $usd));   // 100 − 33.33 − 0.50
    }

    public function test_fees_and_net_amounts_always_sum_back_to_what_the_user_spent(): void
    {
        $usd = $this->usd();
        $btc = $this->asset('btc', '61234.5678', 8, ['buy_charge_rate' => '1.75']);
        $w   = $this->wallet();
        $this->fund($w, $usd, '1000');

        foreach (['0.01', '3.33', '99.99', '123.45'] as $spend) {
            $before = $this->balance($w, $usd);
            app(FundsService::class)->initiateBuy($w, ['asset_id' => $btc->id, 'usd_amount' => $spend]);
            $after = $this->balance($w, $usd);

            $this->assertSame(\App\Support\Money::of($spend), \App\Support\Money::sub($before, $after), "spent {$spend}");
        }
    }

    public function test_demo_seed_data_is_ledger_consistent(): void
    {
        $this->seed(\Database\Seeders\TestSeeder::class);
        $ledger = app(LedgerService::class);

        $negative = 0;
        foreach (\App\Models\Wallet::all() as $wallet) {
            foreach (\App\Models\Asset::all() as $asset) {
                if (\App\Support\Money::cmp($ledger->balanceFor($wallet->id, $asset->id), 0) < 0) {
                    $negative++;
                }
            }
        }

        $this->assertSame(0, $negative);
        $this->assertGreaterThan(0, Transaction::count());
    }
}
