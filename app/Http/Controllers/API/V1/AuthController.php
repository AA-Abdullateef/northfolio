<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\ForgotPasswordRequest;
use App\Http\Requests\API\V1\LoginRequest;
use App\Http\Requests\API\V1\ProfileSetupRequest;
use App\Http\Requests\API\V1\ResendRegistrationOtpRequest;
use App\Http\Requests\API\V1\ResetPasswordRequest;
use App\Http\Requests\API\V1\VerifyRegistrationOtpRequest;
use App\Http\Requests\API\V1\VerifyResetOtpRequest;
use App\Http\Resources\API\V1\UserResource;
use App\Models\User;
use App\Models\Wallet;
use App\Services\IdentityService;
use App\Services\NotificationService;
use App\Services\OtpService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly IdentityService     $identityService,
        private readonly OtpService          $otpService,
        private readonly NotificationService $notificationService,
    ) {}

    public function completeProfile(ProfileSetupRequest $request): JsonResponse
    {
        $wallet = $request->user();

        if (! $wallet instanceof Wallet) {
            return $this->error('Wallet authentication required.');
        }

        if ($wallet->user_id !== null) {
            return $this->error('Wallet already linked to a user.', null, 422);
        }

        $result = $this->identityService->registerAndLinkWallet(
            $wallet,
            $request->validated()
        );

        return $this->success(
            'Registration completed. Please verify your email.',
            [
                'access_token' => $result['access_token'],
                'user' => new UserResource($result['user']),
                'wallet' => [
                    'id' => $result['wallet']->id,
                    'fingerprint' => $result['wallet']->fingerprint,
                    'public_id' => $result['wallet']->public_id,
                ],
                'requires_email_verification' => true,
            ]
        );
    }

    public function verifyRegistrationOtp(VerifyRegistrationOtpRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->firstOrFail();

        try {
            $this->otpService->verifyRegistrationOtp($user, $request->otp);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $result = $this->identityService->verifyEmail($user);

        return $this->success('Email verified.', [
            'access_token' => $result['access_token'],
            'user'  => new UserResource($result['user']),
        ]);
    }

    public function resendRegistrationOtp(ResendRegistrationOtpRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->firstOrFail();

        $otp = $this->otpService->createRegistrationOtp($user);
        $this->notificationService->sendOtp($user, $otp, 'registration');

        return $this->success('A new OTP has been sent to your email.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->identityService->login($request->user, $request->password);
        } catch (ValidationException) {
            return $this->error('Invalid credentials.', null, 401);
        }

        return $this->success('Login successful.', [
            'access_token' => $result['access_token'],
            'user'  => new UserResource($result['user']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $identity = $request->user();

        if ($identity instanceof Wallet) {
            return $this->success('Authenticated wallet.', [
                'auth_type' => 'wallet',
                'wallet' => [
                    'id'          => $identity->id,
                    'fingerprint' => $identity->fingerprint,
                    'public_id'  => $identity->public_id,
                    'has_user'    => $identity->user_id !== null,
                ],
                'user' => $identity->user
                    ? new UserResource($identity->user->load('profile', 'photo'))
                    : null,
                'requires_profile' => $identity->user_id === null,
            ]);
        }

        return $this->success('Authenticated user.', [
            'auth_type' => 'user',
            'user' => new UserResource($identity->load('profile', 'photo')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success('Logged out successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->firstOrFail();

        $otp = $this->otpService->createPasswordResetOtp($user);
        $this->notificationService->sendOtp($user, $otp, 'password_reset');

        return $this->success('Password reset OTP sent to your email.');
    }

    public function verifyResetOtp(VerifyResetOtpRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->firstOrFail();

        try {
            $resetToken = $this->otpService->verifyPasswordResetOtp($user, $request->otp);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success('OTP verified.', ['reset_token' => $resetToken]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->firstOrFail();

        try {
            $otp = $this->otpService->validateResetToken($user, $request->reset_token);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $this->identityService->resetPassword($user, $request->password);
        $this->otpService->consumeResetToken($otp);

        return $this->success('Password reset. Please log in.');
    }
}
