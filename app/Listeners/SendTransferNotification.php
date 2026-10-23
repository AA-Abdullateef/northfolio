<?php

namespace App\Listeners;

use App\Events\TransferInitiated;
use App\Services\NotificationService;

class SendTransferNotification
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {}

    public function handle(TransferInitiated $event): void
    {
        $this->notifications->sendTransferInitiated(
            $event->wallet,
            $event->transaction
        );
    }
}
