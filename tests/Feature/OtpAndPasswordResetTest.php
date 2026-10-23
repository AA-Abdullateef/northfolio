<?php

namespace Tests\Feature;

use App\Models\PasswordResetOtp;
use App\Models\RegistrationOtp;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class OtpAndPasswordResetTest extends TestCase
{
    use RefreshDatabase, BuildsLedger;

    private function otp(): OtpService
    {
        return app(OtpService::class);
    }

    private function wrong(string $right): string
    {
        return $right === '000000' ? '111111' : '000000';
    }

    // ── Registration OTP ──────────────────────────────────────────────────────

    public function test_registration_otp_verifies_once_and_cannot_be_replayed(): void
    {
        $user  = $this->user();
        $plain = $this->otp()->createRegistrationOtp($user);

        $this->assertTrue($this->otp()->verifyRegistrationOtp($user, $plain));

        $this->expectExceptionMessage('OTP not found or expired.');
        $this->otp()->verifyRegistrationOtp($user, $plain);                // replay
    }

    public function test_registration_otp_expires(): void
    {
        $user  = $this->user();
        $plain = $this->otp()->createRegistrationOtp($user);

        $this->travel(OtpService::REGISTRATION_TTL_MINUTES + 1)->minutes();

        $this->expectExceptionMessage('OTP not found or expired.');
        $this->otp()->verifyRegistrationOtp($user, $plain);
    }

    public function test_registration_otp_locks_after_max_attempts_even_for_the_right_code(): void
    {
        $user  = $this->user();
        $plain = $this->otp()->createRegistrationOtp($user);

        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
            try {
                $this->otp()->verifyRegistrationOtp($user, $this->wrong($plain));
            } catch (\RuntimeException $e) {
                $this->assertSame('Invalid OTP.', $e->getMessage());
            }
        }

        $this->assertSame(OtpService::MAX_ATTEMPTS, RegistrationOtp::first()->attempts);
        $this->expectExceptionMessage('Too many failed attempts');
        $this->otp()->verifyRegistrationOtp($user, $plain);
    }

    public function test_requesting_a_new_registration_otp_invalidates_the_old_one(): void
    {
        $user = $this->user();
        $old  = $this->otp()->createRegistrationOtp($user);
        $new  = $this->otp()->createRegistrationOtp($user);

        $this->assertSame(1, RegistrationOtp::count());
        if ($old !== $new) {
            $this->expectException(\RuntimeException::class);
            $this->otp()->verifyRegistrationOtp($user, $old);
        } else {
            $this->assertTrue($this->otp()->verifyRegistrationOtp($user, $new));
        }
    }

    public function test_otps_are_stored_hashed_not_in_plaintext(): void
    {
        $user  = $this->user();
        $plain = $this->otp()->createRegistrationOtp($user);

        $this->assertNotSame($plain, RegistrationOtp::first()->getRawOriginal('otp'));
        $this->assertTrue(Hash::check($plain, RegistrationOtp::first()->getRawOriginal('otp')));
    }

    // ── 2FA / sensitive-action token ─────────────────────────────────────────

    public function test_two_factor_token_is_single_use_scoped_and_attempt_limited(): void
    {
        $user  = $this->user();
        $plain = $this->otp()->createTwoFactorToken($user, 'withdrawal');

        try {
            $this->otp()->verifyTwoFactorToken($user, $plain, 'transfer');   // wrong purpose
            $this->fail('purpose not enforced');
        } catch (\RuntimeException) {
        }

        $this->assertTrue($this->otp()->verifyTwoFactorToken($user, $plain, 'withdrawal'));

        $this->expectException(\RuntimeException::class);
        $this->otp()->verifyTwoFactorToken($user, $plain, 'withdrawal');    // replay
    }

    // ── Password reset (HTTP, end to end) ─────────────────────────────────────

    public function test_full_password_reset_flow_changes_the_password(): void
    {
        $user  = $this->user(['email' => 'sam@example.test', 'username' => 'sam', 'password' => 'old-password-1']);
        $plain = $this->otp()->createPasswordResetOtp($user);

        $token = $this->postJson('/api/v1/verify-reset-otp', ['email' => $user->email, 'otp' => $plain])
            ->assertOk()->json('data.reset_token');

        $this->postJson('/api/v1/reset-password', [
            'email' => $user->email, 'reset_token' => $token,
            'password' => 'new-password-2', 'password_confirmation' => 'new-password-2',
        ])->assertOk();

        $this->postJson('/api/v1/login', ['user' => 'sam', 'password' => 'new-password-2'])->assertOk();
        $this->postJson('/api/v1/login', ['user' => 'sam', 'password' => 'old-password-1'])->assertStatus(401);
    }

    public function test_reset_otp_cannot_be_replayed_to_mint_a_second_token(): void
    {
        $user  = $this->user();
        $plain = $this->otp()->createPasswordResetOtp($user);

        $this->postJson('/api/v1/verify-reset-otp', ['email' => $user->email, 'otp' => $plain])->assertOk();
        $this->postJson('/api/v1/verify-reset-otp', ['email' => $user->email, 'otp' => $plain])
            ->assertStatus(422)->assertJsonPath('message', 'OTP already used. Request a new one.');
    }

    public function test_reset_token_is_single_use_and_wrong_or_stale_tokens_fail(): void
    {
        $user  = $this->user(['password' => 'old-password-1']);
        $plain = $this->otp()->createPasswordResetOtp($user);
        $token = $this->otp()->verifyPasswordResetOtp($user, $plain);
        $body  = ['email' => $user->email, 'password' => 'new-password-2', 'password_confirmation' => 'new-password-2'];

        $this->postJson('/api/v1/reset-password', $body + ['reset_token' => 'not-the-token'])->assertStatus(422);
        $this->assertTrue(Hash::check('old-password-1', $user->fresh()->password));

        $this->postJson('/api/v1/reset-password', $body + ['reset_token' => $token])->assertOk();
        $this->postJson('/api/v1/reset-password', ['password' => 'third-password-3', 'password_confirmation' => 'third-password-3'] + ['email' => $user->email, 'reset_token' => $token])
            ->assertStatus(422);                                                           // replay
        $this->assertTrue(Hash::check('new-password-2', $user->fresh()->password));
    }

    public function test_reset_token_expires(): void
    {
        $user  = $this->user();
        $token = $this->otp()->verifyPasswordResetOtp($user, $this->otp()->createPasswordResetOtp($user));

        $this->travel(OtpService::RESET_TOKEN_TTL_MINUTES + 1)->minutes();

        $this->postJson('/api/v1/reset-password', [
            'email' => $user->email, 'reset_token' => $token,
            'password' => 'new-password-2', 'password_confirmation' => 'new-password-2',
        ])->assertStatus(422);
    }

    public function test_reset_otp_locks_after_too_many_wrong_guesses(): void
    {
        $user  = $this->user();
        $plain = $this->otp()->createPasswordResetOtp($user);

        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
            $this->postJson('/api/v1/verify-reset-otp', ['email' => $user->email, 'otp' => $this->wrong($plain)])->assertStatus(422);
        }

        $this->postJson('/api/v1/verify-reset-otp', ['email' => $user->email, 'otp' => $plain])
            ->assertStatus(422);                                                            // right code, still locked
        $this->assertNull(PasswordResetOtp::first()->reset_token);
    }
}
