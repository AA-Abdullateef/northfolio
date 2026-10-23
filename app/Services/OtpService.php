<?php

namespace App\Services;

use App\Models\PasswordResetOtp;
use App\Models\RegistrationOtp;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OtpService
{
    public const REGISTRATION_TTL_MINUTES = 10;
    public const RESET_OTP_TTL_MINUTES    = 10;
    public const RESET_TOKEN_TTL_MINUTES  = 15;
    public const TWO_FACTOR_TTL_MINUTES   = 10;
    public const MAX_ATTEMPTS             = 5;

    // ─── Registration OTP ───────────────────────────────────────────────────────

    /**
     * Create (or replace) a registration OTP for the given user.
     * Returns the plain OTP for sending in email.
     */
    public function createRegistrationOtp(User $user): string
    {
        // Invalidate any previous unused OTPs for this user
        $user->registrationOtps()->where('used', false)->delete();

        $plain = $this->generateNumeric(6);

        RegistrationOtp::create([
            'user_id'    => $user->id,
            'otp'        => Hash::make($plain),
            'attempts'   => 0,
            'used'       => false,
            'expires_at' => now()->addMinutes(self::REGISTRATION_TTL_MINUTES),
        ]);

        return $plain;
    }

    /**
     * Verify a registration OTP. Returns true on success, throws on failure.
     *
     * @throws \RuntimeException
     */
    public function verifyRegistrationOtp(User $user, string $plain): bool
    {
        $otp = RegistrationOtp::activeForUser($user->id)->latest()->first();

        if (! $otp) {
            throw new \RuntimeException('OTP not found or expired.');
        }

        if ($otp->hasExceededAttempts(self::MAX_ATTEMPTS)) {
            throw new \RuntimeException('Too many failed attempts. Please request a new OTP.');
        }

        if (! Hash::check($plain, $otp->otp)) {
            $otp->increment('attempts');
            throw new \RuntimeException('Invalid OTP.');
        }

        $otp->update(['used' => true]);

        return true;
    }

    // ─── Password Reset OTP ─────────────────────────────────────────────────────

    public function createPasswordResetOtp(User $user): string
    {
        $user->passwordResetOtps()->where('used', false)->delete();

        $plain = $this->generateNumeric(6);

        PasswordResetOtp::create([
            'user_id'    => $user->id,
            'otp'        => Hash::make($plain),
            'attempts'   => 0,
            'used'       => false,
            'expires_at' => now()->addMinutes(self::RESET_OTP_TTL_MINUTES),
        ]);

        return $plain;
    }

    /**
     * Verify password reset OTP and issue a reset token.
     * Returns the plain reset token on success.
     *
     * @throws \RuntimeException
     */
    public function verifyPasswordResetOtp(User $user, string $plain): string
    {
        $otp = PasswordResetOtp::activeForUser($user->id)->latest()->first();

        if (! $otp) {
            throw new \RuntimeException('OTP not found or expired.');
        }

        if ($otp->hasExceededAttempts(self::MAX_ATTEMPTS)) {
            throw new \RuntimeException('Too many failed attempts. Please request a new OTP.');
        }

        if (! Hash::check($plain, $otp->otp)) {
            $otp->increment('attempts');
            throw new \RuntimeException('Invalid OTP.');
        }

        // Replay protection: an OTP that already produced a reset token is spent.
        if ($otp->reset_token !== null) {
            throw new \RuntimeException('OTP already used. Request a new one.');
        }

        // Issue reset token valid for another window
        $resetToken = Str::random(64);

        $otp->update([
            'reset_token' => Hash::make($resetToken),
            'expires_at'  => now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES),
        ]);

        return $resetToken;
    }

    /**
     * Validate a reset token before allowing password change.
     *
     * @throws \RuntimeException
     */
    public function validateResetToken(User $user, string $token): PasswordResetOtp
    {
        $otp = $user->passwordResetOtps()
                    ->where('used', false)
                    ->whereNotNull('reset_token')
                    ->where('expires_at', '>', now())
                    ->latest()
                    ->first();

        if (! $otp || ! Hash::check($token, $otp->reset_token)) {
            throw new \RuntimeException('Invalid or expired reset token.');
        }

        return $otp;
    }

    public function consumeResetToken(PasswordResetOtp $otp): void
    {
        $otp->update(['used' => true]);
    }

    // ─── Two-Factor / Sensitive Action Tokens ───────────────────────────────────

    /**
     * Create a scoped 2FA token for a user and purpose.
     * Returns the plain token for email dispatch.
     */
    public function createTwoFactorToken(User $user, string $purpose = 'two_factor'): string
    {
        // Delete any previous tokens for same user + purpose
        UserToken::forUser($user->id)->forPurpose($purpose)->delete();

        $plain = $this->generateNumeric(5);

        UserToken::create([
            'user_id'    => $user->id,
            'token'      => Hash::make($plain),
            'purpose'    => $purpose,
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(self::TWO_FACTOR_TTL_MINUTES),
        ]);

        return $plain;
    }

    /**
     * Verify a 2FA token scoped to user + purpose.
     *
     * @throws \RuntimeException
     */
    public function verifyTwoFactorToken(User $user, string $plain, string $purpose = 'two_factor'): bool
    {
        $token = UserToken::forUser($user->id)
                          ->forPurpose($purpose)
                          ->where('expires_at', '>', now())
                          ->latest()
                          ->first();

        if (! $token) {
            throw new \RuntimeException('Token not found or expired.');
        }

        if ($token->hasExceededAttempts(self::MAX_ATTEMPTS)) {
            throw new \RuntimeException('Too many failed attempts.');
        }

        if (! Hash::check($plain, $token->token)) {
            $token->increment('attempts');
            throw new \RuntimeException('Invalid token.');
        }

        $token->delete();

        return true;
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function generateNumeric(int $digits): string
    {
        return str_pad((string) random_int(0, (10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
    }
}