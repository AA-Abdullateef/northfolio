<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\WalletImportRequest;
use App\Http\Resources\API\V1\UserResource;
use App\Services\WalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WalletSetupController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly WalletService $walletService) {}

    /**
     * Step 1 — Generate a 12-word mnemonic and cache it under a setup token.
     * Mnemonic is NOT stored in DB at this point.
     */
    public function generate(): JsonResponse
    {
        $result = $this->walletService->generateMnemonic();

        return $this->success(
            'Demo Wallet ID generated. Write down all 12 words in order — they will not be shown again. This is a demo identity, not a real blockchain wallet.',
            $result
        );
    }

    /**
     * Step 2 — Request 3 random word positions to challenge the user.
     */
    public function challenge(Request $request): JsonResponse
    {
        $request->validate(['setup_token' => ['required', 'string']]);

        try {
            $result = $this->walletService->challenge($request->setup_token);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), $e->errors(), 422);
        }

        return $this->success('Challenge issued. Confirm the words at the given positions.', $result);
    }

    /**
     * Step 3 — Verify challenge words, create wallet + user, return Sanctum token.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'setup_token' => ['required', 'string'],
            'words'       => ['required', 'array', 'min:3'],
        ]);

        try {
            $result = $this->walletService->verifyAndCreate(
                $request->setup_token,
                $request->words
            );
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), $e->errors(), 422);
        }

        return $this->success('Demo wallet created. This is a demo identity, not a real blockchain wallet.', [
            'access_token'            => $result['access_token'],
            'auth_type'        => $result['auth_type'],
            'wallet'           => $this->walletPayload($result['wallet']),
            'user'             => null,
            'requires_profile' => true,
        ], 201);
    }

    /**
     * Import an existing wallet by providing the 12-word mnemonic phrase.
     */
    public function import(WalletImportRequest $request): JsonResponse
    {
        try {
            $result = $this->walletService->importByMnemonic($request->mnemonic);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), $e->errors(), 422);
        }

        return $this->success('Demo wallet imported.', [
            'access_token'            => $result['access_token'],
            'auth_type'        => $result['auth_type'],
            'wallet'           => $this->walletPayload($result['wallet']),
            'user'             => $result['user'] ? new UserResource($result['user']) : null,
            'requires_profile' => $result['requires_profile'],
        ]);
    }

    /**
     * Look up a recipient wallet by fingerprint or public ID.
     * Used by the transfer form to resolve a human-readable identifier
     * into a wallet ID before submitting a transfer request.
     *
     * Deliberately returns the minimum needed — no mnemonic, no user PII.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:8'],
        ]);

        $query = trim($request->query('query'));

        $wallet = \App\Models\Wallet::where('fingerprint', $query)
            ->orWhere('public_id', $query)
            ->first();

        if (! $wallet) {
            return $this->error('No wallet found matching that identifier.', [], 404);
        }

        // Prevent users from transferring to themselves.
        if ($wallet->id === $request->wallet()->id) {
            return $this->error('You cannot transfer to your own wallet.', [], 422);
        }

        return $this->success('Wallet found.', [
            'wallet' => [
                'id'          => $wallet->id,
                'fingerprint' => $wallet->fingerprint,
                'public_id'  => $wallet->public_id,
            ],
        ]);
    }

    private function walletPayload($wallet): array
    {
        return [
            'id'          => $wallet->id,
            'fingerprint' => $wallet->fingerprint,
            'public_id'  => $wallet->public_id,
            'has_user'    => $wallet->user_id !== null,
        ];
    }
}