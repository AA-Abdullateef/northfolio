<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\NotificationService;
use App\Services\OtpService;

class SendRegistrationNotifications
{
    public function __construct(
        private readonly OtpService          $otpService,
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        $otp = $this->otpService->createRegistrationOtp($user);
        $this->notificationService->sendOtp($user, $otp, 'registration');
    }
}
