<?php

namespace App\Listeners;

use App\Events\WithdrawalInitiated;
use App\Services\NotificationService;

class SendWithdrawalNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(WithdrawalInitiated $event): void
    {
        $transaction = $event->transaction->loadMissing('asset');

        $this->notificationService->sendTransactionNotice($event->wallet, $transaction);
    }
}
