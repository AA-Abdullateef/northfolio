<?php

namespace App\Listeners;

use App\Events\TransactionCancelled;
use App\Services\NotificationService;

class SendTransactionCancelledNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(TransactionCancelled $event): void
    {
        $transaction = $event->transaction->loadMissing('asset', 'wallet.user');

        if (! $transaction->wallet) {
            return;
        }

        $this->notificationService->sendTransactionNotice($transaction->wallet, $transaction);
    }
}
