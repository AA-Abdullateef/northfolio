<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $assets = [
            ['name' => 'usd',  'label' => 'US Dollar',       'icon' => 'usd.png', 'price_source' => 'manual',       'price_source_id' => null,      'current_price' => 1, 'decimals' => 2],
            ['name' => 'btc',  'label' => 'Bitcoin',          'icon' => 'btc.png', 'price_source' => 'coingecko',    'price_source_id' => 'bitcoin'],
            ['name' => 'eth',  'label' => 'Ethereum',         'icon' => 'eth.png', 'price_source' => 'coingecko',    'price_source_id' => 'ethereum'],
            ['name' => 'usdt', 'label' => 'Tether USD',       'icon' => 'usdt.png', 'price_source' => 'coingecko',   'price_source_id' => 'tether'],
            ['name' => 'trx',  'label' => 'TRON',             'icon' => 'trx.png', 'price_source' => 'coingecko',    'price_source_id' => 'tron'],
            ['name' => 'bnb',  'label' => 'BNB',              'icon' => 'bnb.png', 'price_source' => 'coingecko',    'price_source_id' => 'binancecoin'],
            ['name' => 'xrp',  'label' => 'XRP',              'icon' => 'xrp.png', 'price_source' => 'coingecko',    'price_source_id' => 'ripple'],
            ['name' => 'aapl', 'label' => 'Apple Inc (AAPL)', 'icon' => 'aapl.png', 'price_source' => 'alphavantage', 'price_source_id' => 'AAPL'],
            ['name' => 'tsla', 'label' => 'Tesla (TSLA)',     'icon' => 'tsla.png', 'price_source' => 'alphavantage', 'price_source_id' => 'TSLA'],
        ];

        foreach ($assets as $asset) {
            $existing = Asset::firstOrNew(['name' => $asset['name']]);

            if (! $existing->exists) {
                $existing->id = (string) Str::uuid();
                $existing->active = true;
            }

            $existing->fill($asset)->save();
        }

        $this->command->info('Assets seeded (' . count($assets) . ' assets).');
    }
}
