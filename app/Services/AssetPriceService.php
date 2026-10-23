<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssetPriceService
{
    /**
     * Sync USD prices for every active, auto-priced asset.
     * CoinGecko supports fetching many coins in a single request, so all
     * crypto assets go out as one batch call. AlphaVantage's free tier has
     * no batch quote endpoint, so equities are synced one request each.
     */
    public function syncAll(): array
    {
        $result = ['synced' => 0, 'skipped' => 0, 'failed' => 0];

        $assets = Asset::active()
            ->whereNotNull('price_source')
            ->where('price_source', '!=', 'manual')
            ->get();

        $this->syncCoinGeckoBatch($assets->where('price_source', 'coingecko'), $result);

        foreach ($assets->where('price_source', 'alphavantage') as $asset) {
            $this->syncAlphaVantage($asset, $result);
        }

        foreach ($assets->where('price_source', 'finnhub') as $asset) {
            $this->syncFinnhub($asset, $result);
        }

        return $result;
    }

    /**
     * Sync a single asset by id — used by the admin "sync now" action.
     */
    public function syncOne(Asset $asset): string
    {
        $result = ['synced' => 0, 'skipped' => 0, 'failed' => 0];

        if ($asset->price_source === 'coingecko') {
            $this->syncCoinGeckoBatch(collect([$asset]), $result);
        } elseif ($asset->price_source === 'alphavantage') {
            $this->syncAlphaVantage($asset, $result);
        } elseif ($asset->price_source === 'finnhub') {
            $this->syncFinnhub($asset, $result);
        } else {
            return 'skipped';
        }

        return match (true) {
            $result['synced'] > 0 => 'synced',
            $result['failed'] > 0 => 'failed',
            default => 'skipped',
        };
    }

    // ─── Private ─────────────────────────────────────────────────────────────────

    private function syncCoinGeckoBatch(Collection $assets, array &$result): void
    {
        if ($assets->isEmpty()) {
            return;
        }

        if (! $this->hasApiCredentials('coingecko')) {
            Log::warning('Price sync skipped for CoinGecko: missing API key.');
            $result['skipped'] += $assets->count();
            return;
        }

        $ids = $assets->pluck('price_source_id')->filter()->unique()->implode(',');

        if ($ids === '') {
            $result['skipped'] += $assets->count();
            return;
        }

        try {
            $response = Http::timeout(15)->retry(2, 250)
                ->withHeaders(array_filter([
                    'x-cg-demo-api-key' => config('services.coingecko.key'),
                ]))
                ->get('https://api.coingecko.com/api/v3/simple/price', [
                    'ids' => $ids,
                    'vs_currencies' => 'usd',
                ]);
        } catch (\Throwable $e) {
            Log::error("CoinGecko batch price fetch failed: {$e->getMessage()}");
            $result['failed'] += $assets->count();
            return;
        }

        if (! $response->successful()) {
            Log::warning("CoinGecko batch price fetch returned HTTP {$response->status()}.");
            $result['failed'] += $assets->count();
            return;
        }

        // Response shape: {"bitcoin": {"usd": 65000.12}, "tether": {"usd": 1.0}, ...}
        $prices = $response->json() ?? [];

        foreach ($assets as $asset) {
            $price = $prices[$asset->price_source_id]['usd'] ?? null;

            if ($price === null || ! is_numeric($price) || (float) $price <= 0) {
                Log::warning("Price sync skipped for {$asset->name}: invalid or missing price in CoinGecko response.");
                $result['skipped']++;
                continue;
            }

            $asset->update([
                'current_price' => $price,
                'price_updated_at' => now(),
            ]);

            $result['synced']++;
        }
    }

    private function syncAlphaVantage(Asset $asset, array &$result): void
    {
        if (! $this->hasApiCredentials('alphavantage')) {
            Log::warning("Price sync skipped for {$asset->name}: missing AlphaVantage API key.");
            $result['skipped']++;
            return;
        }

        try {
            $response = Http::timeout(15)->retry(2, 250)->get('https://www.alphavantage.co/query', [
                'function' => 'GLOBAL_QUOTE',
                'symbol' => $asset->price_source_id,
                'apikey' => config('services.alphavantage.key'),
            ]);
        } catch (\Throwable $e) {
            Log::error("AlphaVantage price fetch failed for {$asset->name}: {$e->getMessage()}");
            $result['failed']++;
            return;
        }

        if (! $response->successful()) {
            Log::warning("AlphaVantage price fetch for {$asset->name} returned HTTP {$response->status()}.");
            $result['failed']++;
            return;
        }

        // The "05. price" key contains a literal period as part of the key name,
        // not a nesting separator — index it directly, don't use dot-notation ->json('Global Quote.05. price').
        $quote = $response->json('Global Quote', []);
        $price = $quote['05. price'] ?? null;

        if ($price === null || ! is_numeric($price) || (float) $price <= 0) {
            Log::warning("Price sync skipped for {$asset->name}: invalid or null price returned.");
            $result['skipped']++;
            return;
        }

        $asset->update([
            'current_price' => $price,
            'price_updated_at' => now(),
        ]);

        $result['synced']++;
    }

    private function syncFinnhub(Asset $asset, array &$result): void
    {
        if (! $this->hasApiCredentials('finnhub')) {
            Log::warning("Price sync skipped for {$asset->name}: missing Finnhub API key.");
            $result['skipped']++;
            return;
        }

        try {
            $response = Http::timeout(15)->retry(2, 250)->get('https://finnhub.io/api/v1/quote', [
                'symbol' => $asset->price_source_id,
                'token' => config('services.finnhub.key'),
            ]);
        } catch (\Throwable $e) {
            Log::error("Finnhub price fetch failed for {$asset->name}: {$e->getMessage()}");
            $result['failed']++;
            return;
        }

        if (! $response->successful()) {
            Log::warning("Finnhub price fetch for {$asset->name} returned HTTP {$response->status()}.");
            $result['failed']++;
            return;
        }

        // Response shape: {"c": 261.74, "d": 0.55, "dp": 0.21, "h": ..., "l": ..., "o": ..., "pc": ..., "t": ...}
        // "c" is the current price. A quote for an unknown symbol still returns
        // HTTP 200 with every field (including "c") at 0 — treat that as no price,
        // not a real $0 asset.
        $price = $response->json('c');

        if ($price === null || ! is_numeric($price) || (float) $price <= 0) {
            Log::warning("Price sync skipped for {$asset->name}: invalid, zero, or null price returned from Finnhub.");
            $result['skipped']++;
            return;
        }

        $asset->update([
            'current_price' => $price,
            'price_updated_at' => now(),
        ]);

        $result['synced']++;
    }

    private function hasApiCredentials(string $source): bool
    {
        return match ($source) {
            'coingecko' => filled(config('services.coingecko.key')) || config('services.coingecko.require_key') !== true,
            'alphavantage' => filled(config('services.alphavantage.key')),
            'finnhub' => filled(config('services.finnhub.key')),
            default => true,
        };
    }
}
