<?php

namespace Tests\Concerns;

use App\Models\Asset;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Support\Str;

trait BuildsLedger
{
    protected function asset(string $name, ?string $price = null, int $decimals = 8, array $extra = []): Asset
    {
        return Asset::forceCreate(array_merge([
            'id' => (string) Str::uuid(), 'name' => $name, 'label' => strtoupper($name), 'active' => true,
            'price_source' => $price === null ? null : 'manual', 'current_price' => $price, 'decimals' => $decimals,
        ], $extra));
    }

    protected function usd(): Asset
    {
        return Asset::whereRaw('lower(name) = ?', ['usd'])->first() ?? $this->asset('usd', '1', 2);
    }

    protected function wallet(?User $user = null): Wallet
    {
        return Wallet::forceCreate([
            'id' => (string) Str::uuid(), 'user_id' => $user?->id,
            'mnemonic_hash' => hash('sha256', Str::random(16)),
            'fingerprint' => hash('sha256', Str::random(16)),
            'public_id' => hash('sha256', Str::random(16)),
        ]);
    }

    protected function user(array $attrs = []): User
    {
        return User::forceCreate(array_merge([
            'id' => (string) Str::uuid(), 'username' => 'u' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)) . '@example.test', 'password' => 'password-123',
            'status' => 'active', 'profile_completed' => true, 'email_verified_at' => now(),
        ], $attrs));
    }

    /** A user that owns a wallet. @return array{0: User, 1: Wallet} */
    protected function userWithWallet(): array
    {
        $user = $this->user();

        return [$user, $this->wallet($user)];
    }

    protected function fund(Wallet $wallet, Asset $asset, string $amount): Transaction
    {
        return Transaction::create([
            'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'type' => 'deposit', 'amount' => $amount,
            'status' => 'completed', 'reference' => (string) Str::uuid(), 'external_id' => 'pi_' . Str::random(12),
            'meta' => ['source' => 'test'],
        ]);
    }

    protected function balance(Wallet $wallet, Asset $asset): string
    {
        return app(LedgerService::class)->balanceFor($wallet->id, $asset->id);
    }
}
