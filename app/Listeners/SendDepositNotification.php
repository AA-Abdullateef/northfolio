<?php

namespace App\Listeners;

use App\Events\DepositInitiated;
use App\Services\NotificationService;

class SendDepositNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(DepositInitiated $event): void
    {
        // Eager-load asset so the mail view can reference it
        $transaction = $event->transaction->loadMissing('asset');

        $this->notificationService->sendDepositInitiated($event->wallet, $transaction);
    }
}
