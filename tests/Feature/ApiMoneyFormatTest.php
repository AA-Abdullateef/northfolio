<?php

namespace Tests\Feature;

use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

/** Every monetary value in an API response is an 8-dp decimal STRING, never a JSON number. */
class ApiMoneyFormatTest extends TestCase
{
    use RefreshDatabase, BuildsLedger;

    private const MONEY_KEYS = [
        'amount', 'balance', 'usd_balance', 'total_usd', 'value_usd', 'price_usd',
        'swap_charge_rate', 'buy_charge_rate', 'sell_charge_rate',
    ];

    private function assertMoneyStrings(mixed $node, string $path = ''): int
    {
        $checked = 0;
        if (! is_array($node)) {
            return 0;
        }
        foreach ($node as $k => $v) {
            if (is_string($k) && in_array($k, self::MONEY_KEYS, true) && $v !== null) {
                $this->assertIsString($v, "{$path}.{$k} must be a string, got " . gettype($v));
                $this->assertMatchesRegularExpression('/^-?\d+\.\d{8}$/', $v, "{$path}.{$k}");
                $checked++;
            }
            $checked += $this->assertMoneyStrings($v, "{$path}.{$k}");
        }

        return $checked;
    }

    public function test_money_fields_are_strings_across_money_endpoints(): void
    {
        $usd = $this->usd();
        $btc = $this->asset('btc', '60000.5', 8, ['buy_charge_rate' => '1.50', 'swap_charge_rate' => '0.25', 'sell_charge_rate' => '2.00']);
        [, $w] = $this->userWithWallet();
        $this->fund($w, $usd, '1000.00');
        $this->fund($w, $btc, '0.00000001');
        Sanctum::actingAs($w);

        $checked = 0;
        foreach (['/dashboard', '/assets', "/assets/{$btc->id}/transactions", '/transactions', '/buy/options', '/sell/options', '/swap/options', '/withdrawals/options', '/transfers/options', '/deposits/options'] as $url) {
            $res = $this->getJson('/api/v1' . $url)->assertOk();
            $checked += $this->assertMoneyStrings($res->json('data'), $url);
        }

        $this->assertGreaterThan(30, $checked, 'expected many money fields to be inspected');
    }

    public function test_dashboard_totals_are_exact_strings(): void
    {
        $usd = $this->usd();
        $btc = $this->asset('btc', '60000.5');
        [, $w] = $this->userWithWallet();
        $this->fund($w, $usd, '0.10');
        $this->fund($w, $usd, '0.20');           // float 0.1+0.2 != 0.3
        $this->fund($w, $btc, '0.00000003');
        Sanctum::actingAs($w);

        $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');

        $this->assertSame('0.30000000', collect($data['asset_balances'])->firstWhere('asset.name', 'usd')['balance']);
        $this->assertSame('0.30000000', $data['balances'][$usd->id]);
        // BTC is worth 0.00000003 × 60000.5 = 0.0018… → 0.00 at cent precision, so the total stays exactly 0.30.
        $this->assertSame('0.30000000', $data['balance']);
        $this->assertSame('0.30000000', $data['portfolio_usd']['total_usd']);
    }
}
