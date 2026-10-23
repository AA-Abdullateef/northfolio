<?php

namespace App\Listeners;

use App\Events\TransactionCompleted;
use App\Services\NotificationService;

class SendTransactionCompletedNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(TransactionCompleted $event): void
    {
        $transaction = $event->transaction->loadMissing('asset', 'wallet.user');

        if (! $transaction->wallet) {
            return;
        }

        $this->notificationService->sendTransactionNotice($transaction->wallet, $transaction);
    }
}
