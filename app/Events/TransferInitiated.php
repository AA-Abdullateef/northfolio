<?php

namespace App\Events;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferInitiated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Wallet      $wallet,
        public readonly Transaction $transaction,
    ) {}
}
