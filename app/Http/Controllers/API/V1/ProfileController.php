<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\ProfilePhotoRequest;
use App\Http\Requests\API\V1\ProfileSetupRequest;
use App\Http\Requests\API\V1\UpdatePasswordRequest;
use App\Http\Requests\API\V1\UpdateProfileRequest;
use App\Http\Resources\API\V1\UserResource;
use App\Models\ProfilePhoto;
use App\Models\User;
use App\Models\Wallet;
use App\Services\IdentityService;
use App\Services\NotificationService;
use App\Services\OtpService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly IdentityService     $identityService,
        private readonly OtpService          $otpService,
        private readonly NotificationService $notificationService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->error('Registration required', [
                'redirect_to' => '/register-profile',
            ], 403);
        }

        return $this->success('Profile.', [
            'user' => new UserResource($user->load('profile', 'photo')),
        ]);
    }

    public function setup(ProfileSetupRequest $request): JsonResponse
    {
        $identity = $request->user();
        $data = $request->validated();

        if (! $identity instanceof Wallet && ! $identity instanceof User) {
            return $this->unauthorized();
        }

        [$user, $token] = DB::transaction(function () use ($identity, $data): array {
            $user = $identity instanceof User
                ? $identity
                : User::create([
                    'id' => (string) Str::uuid(),
                    'status' => 'active',
                ]);

            $user->update([
                'username'           => $data['username'],
                'email'              => strtolower($data['email']),
                'password'           => $data['password'],
                'country_id'         => $data['country_id'],
                'profile_completed'  => true,
                'email_verified_at'  => null,
                'status'             => 'active',
            ]);

            $profile = $user->profile()->firstOrNew(['user_id' => $user->id]);

            if (! $profile->exists) {
                $profile->id = (string) Str::uuid();
            }

            $profile->phone = $data['phone'] ?? null;
            $profile->save();

            if ($identity instanceof Wallet) {
                $identity->update(['user_id' => $user->id]);
                $identity->tokens()->delete();
            }

            $user->tokens()->delete();
            $token = $user->createToken('api')->plainTextToken;

            return [$user->fresh(['profile', 'photo']), $token];
        });

        return $this->success('Registration completed. Please verify your email.', [
            'token' => $token,
            'user' => new UserResource($user->fresh(['profile', 'photo'])),
            'requires_email_verification' => true,
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->error('Registration required', [
                'redirect_to' => '/register-profile',
            ], 403);
        }

        $data = $request->validated();

        $user->update([
            'email'      => $data['email'],
            'country_id' => $data['country_id'] ?? $user->country_id,
        ]);

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            collect($data)->only([
                'first_name', 'last_name', 'gender',
                'phone', 'state', 'address', 'zip', 'dob',
            ])->toArray()
        );

        return $this->success('Profile updated.', [
            'user' => new UserResource($user->fresh(['profile', 'photo'])),
        ]);
    }

    public function uploadPhoto(ProfilePhotoRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->error('Registration required', [
                'redirect_to' => '/register-profile',
            ], 403);
        }

        $file = $request->file('profile_photo');

        // Use $file->extension() — derived from the actual MIME type via finfo,
        // not from the client-supplied filename (getClientOriginalExtension is untrusted).
        $filename = $user->id . '_' . time() . '.' . $file->extension();

        // storeAs() returns the stored path relative to the disk root.
        // We use that return value directly so the DB record always matches
        // the actual file location, even if path construction logic ever changes.
        $path = $file->storeAs('profile', $filename, 'public');

        if ($user->photo) {
            Storage::disk('public')->delete($user->photo->img);
            $user->photo->delete();
        }

        $photo = ProfilePhoto::create([
            'id'      => (string) Str::uuid(),
            'user_id' => $user->id,
            'img'     => $path,
        ]);

        return $this->success('Photo uploaded.', ['url' => $photo->url()]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->error('Registration required', [
                'redirect_to' => '/register-profile',
            ], 403);
        }

        if (! Hash::check($request->old_password, $user->password)) {
            return $this->error('Current password is incorrect.', null, 422);
        }

        $user->update(['password' => $request->password]);

        return $this->success('Password updated.');
    }

    public function settings(Request $request): JsonResponse
    {
        return $this->success('Settings.', [
            'two_factor' => $request->user()->two_factor,
        ]);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->error('Registration required', [
                'redirect_to' => '/register-profile',
            ], 403);
        }

        $this->identityService->deactivate($user);

        return $this->success('Account deactivated.');
    }

    /**
     * Enable 2FA immediately — no confirmation required.
     */
    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->error('Registration required', [
                'redirect_to' => '/register-profile',
            ], 403);
        }

        $user->update(['two_factor' => true]);

        return $this->success('Two-factor authentication enabled.');
    }

    /**
     * Disable 2FA — sends a confirmation OTP first; actual disable happens in confirmTwoFactorDeactivation().
     */
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->error('Registration required', [
                'redirect_to' => '/register-profile',
            ], 403);
        }

        $token = $this->otpService->createTwoFactorToken($user, 'deactivation');
        $this->notificationService->sendOtp($user, $token, 'deactivation');

        return $this->success('A confirmation code has been sent to your email.');
    }

    public function confirmTwoFactorDeactivation(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        if (! $user instanceof User) {
            return $this->error('Registration required', [
                'redirect_to' => '/register-profile',
            ], 403);
        }

        try {
            $this->otpService->verifyTwoFactorToken($user, $request->code, 'deactivation');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $user->update(['two_factor' => false]);

        return $this->success('Two-factor authentication disabled.');
    }
}