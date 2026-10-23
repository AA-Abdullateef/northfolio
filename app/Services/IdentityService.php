<?php

namespace App\Services;

use App\Events\UserRegistered;
use App\Models\Profile;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentityService
{
    public function __construct(private readonly OtpService $otpService) {}

    /**
     * Register a new customer account, link it to the authenticated wallet,
     * and emit the single registration notification.
     */
    public function registerAndLinkWallet(Wallet $wallet, array $data): array
    {
        $result = DB::transaction(function () use ($wallet, $data) {
            $user = User::create([
                'username' => $data['username'],
                'email' => strtolower($data['email']),
                'country_id' => $data['country_id'],
                'password' => $data['password'],
                'status' => 'active',
                'profile_completed' => true,
                'email_verified_at' => null,
            ]);

            Profile::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'phone' => $data['phone'] ?? null,
            ]);

            $wallet->update([
                'user_id' => $user->id,
            ]);

            $wallet->tokens()->delete();
            $user->tokens()->delete();

            return [
                'access_token' => $user->createToken('api')->plainTextToken,
                'user' => $user->fresh(['profile', 'photo']),
                'wallet' => $wallet->fresh(),
            ];
        });

        UserRegistered::dispatch($result['user']);

        return $result;
    }

    /**
     * Attempt login by email or username.
     *
     * @throws ValidationException
     */
    public function login(string $identifier, string $password): array
    {
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($field, strtolower($identifier))->first();

        if (! $user || ! $user->password || ! $user->profile_completed || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'user' => ['Invalid credentials.'],
            ]);
        }

        $user->tokens()->delete();

        $token = $user->createToken('api')->plainTextToken;

        return [
            'access_token' => $token,
            'user'  => $user->load('profile', 'photo'),
        ];
    }

    /**
     * Revoke all tokens — used for logout and deactivation.
     */
    public function deactivate(User $user): void
    {
        $user->tokens()->delete();
        $user->update(['status' => 'deactivated']);
    }

    /**
     * Mark email as verified and issue a Sanctum token.
     */
    public function verifyEmail(User $user): array
    {
        $user->update([
            'email_verified_at' => now(),
            ]);

        $token = $user->createToken('api')->plainTextToken;

        return [
            'access_token' => $token,
            'user'  => $user->load('profile', 'photo'),
        ];
    }

    /**
     * Reset password and revoke all sessions.
     */
    public function resetPassword(User $user, string $newPassword): void
    {
        $user->update(['password' => $newPassword]);
        $user->tokens()->delete();
    }
}
