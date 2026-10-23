<?php

namespace App\Services;

use App\Models\Wallet;
use App\Support\Bip39;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletService
{
    private const SETUP_TOKEN_TTL_MINUTES = 20;
    private const CHALLENGE_WORD_COUNT    = 3;
    private const MAX_VERIFY_ATTEMPTS     = 5;

    /**
     * Step 1: Generate a 12-word BIP-39 mnemonic (CSPRNG entropy + checksum).
     * Words are cached under a setup token — NOT stored in DB at this stage.
     * User must complete the challenge before a wallet record is created.
     */
    public function generateMnemonic(): array
    {
        $phrase     = Bip39::generate(12);
        $words      = explode(' ', $phrase);
        $setupToken = Str::random(64);

        Cache::put(
            "wallet_setup:{$setupToken}",
            ['phrase' => $phrase, 'words' => $words],
            now()->addMinutes(self::SETUP_TOKEN_TTL_MINUTES)
        );

        return [
            'setup_token' => $setupToken,
            'mnemonic'    => $phrase,
        ];
    }

    /**
     * Step 2: Return 3 random 1-based positions for the user to confirm.
     */
    public function challenge(string $setupToken): array
    {
        $data = $this->resolveSetupToken($setupToken);

        // Pick distinct positions with the CSPRNG (partial Fisher–Yates).
        $pool = range(1, 12);
        for ($i = count($pool) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
        }
        $positions = array_slice($pool, 0, self::CHALLENGE_WORD_COUNT);
        sort($positions);

        $data['challenge'] = $positions;
        $data['attempts']  = 0;
        Cache::put(
            "wallet_setup:{$setupToken}",
            $data,
            now()->addMinutes(self::SETUP_TOKEN_TTL_MINUTES)
        );

        return ['positions' => $positions];
    }

    /**
     * Step 3: Verify challenge and create a wallet identity.
     *
     * @param  array  $words  keyed by 1-based position e.g. ['3' => 'word', '7' => 'word', '11' => 'word']
     * @throws ValidationException
     */
    public function verifyAndCreate(string $setupToken, array $words): array
    {
        $data      = $this->resolveSetupToken($setupToken);
        $allWords  = $data['words'];
        $challenge = $data['challenge'] ?? [];

        if ($challenge === []) {
            throw ValidationException::withMessages([
                'setup_token' => ['Request a challenge before verifying.'],
            ]);
        }

        $failed = false;

        foreach ($challenge as $position) {
            $expected = $allWords[$position - 1] ?? null;
            $provided = $words[(string) $position] ?? null;

            if ($expected === null || ! is_string($provided) || strtolower(trim($provided)) !== $expected) {
                $failed = true;
            }
        }

        if ($failed) {
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;

            if ($data['attempts'] >= self::MAX_VERIFY_ATTEMPTS) {
                Cache::forget("wallet_setup:{$setupToken}");
                throw ValidationException::withMessages([
                    'setup_token' => ['Too many failed attempts. Start again to generate a new Demo Wallet ID.'],
                ]);
            }

            Cache::put("wallet_setup:{$setupToken}", $data, now()->addMinutes(self::SETUP_TOKEN_TTL_MINUTES));

            throw ValidationException::withMessages([
                'words' => 'Recovery phrase verification failed. Please check your selected words and try again.',
            ]);
        }

        Cache::forget("wallet_setup:{$setupToken}");

        $wallet = $this->createWalletIdentity($data['phrase']);

        return $this->authenticateWallet($wallet);
    }

    /**
     * Import: locate an existing wallet user by mnemonic fingerprint.
     * No full-table decrypt scan — uses HMAC fingerprint for O(1) lookup.
     *
     * @throws ValidationException
     */
    public function importByMnemonic(string $phrase): array
    {
        if (! Bip39::isValid($phrase)) {
            throw ValidationException::withMessages([
                'mnemonic' => ['Invalid recovery phrase (unknown word, wrong length or bad checksum).'],
            ]);
        }

        $fingerprint   = Wallet::fingerprint($phrase);
        $publicId      = Wallet::publicId($phrase);
        $mnemonicHash  = Wallet::mnemonicHash($phrase);

        $wallet = Wallet::query()
            ->where('fingerprint', $fingerprint)
            ->orWhere('public_id', $publicId)
            ->orWhere('mnemonic_hash', $mnemonicHash)
            ->first();

        if (! $wallet) {
            throw ValidationException::withMessages([
                'mnemonic' => ['No account found for this mnemonic phrase.'],
            ]);
        }

        return $wallet->user
            ? $this->authenticateUserWallet($wallet)
            : $this->authenticateWallet($wallet);
    }

    // ─── Private ─────────────────────────────────────────────────────────────────

    private function resolveSetupToken(string $token): array
    {
        $data = Cache::get("wallet_setup:{$token}");

        if (! $data) {
            throw ValidationException::withMessages([
                'setup_token' => ['Setup token is invalid or has expired.'],
            ]);
        }

        return $data;
    }

    private function createWalletIdentity(string $phrase): Wallet
    {
        return Wallet::updateOrCreate(
            ['fingerprint' => Wallet::fingerprint($phrase)],
            [
                'id'            => (string) Str::uuid(),
                'user_id'       => null,
                'mnemonic_hash' => Wallet::mnemonicHash($phrase),
                'public_id'    => Wallet::publicId($phrase),
            ]
        );
    }

    private function authenticateWallet(Wallet $wallet): array
    {
        $wallet->tokens()->delete();

        $token = $wallet->createToken('wallet')->plainTextToken;

        return [
            'access_token'            => $token,
            'auth_type'        => 'wallet',
            'wallet'           => $wallet->fresh('user'),
            'user'             => null,
            'requires_profile' => false,
        ];
    }

    private function authenticateUserWallet(Wallet $wallet): array
    {
        $user = $wallet->user;

        $user->tokens()->delete();

        $token = $user->createToken('api')->plainTextToken;

        return [
            'access_token'            => $token,
            'auth_type'        => 'user',
            'wallet'           => $wallet,
            'user'             => $user->load('profile', 'photo'),
            'requires_profile' => ! $user->profile_completed,
        ];
    }
}
