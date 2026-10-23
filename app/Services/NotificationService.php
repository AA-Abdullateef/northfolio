<?php

namespace App\Services;

use App\Mail\DepositInitiatedMail;
use App\Mail\OtpMail;
use App\Mail\RegisterMail;
use App\Mail\TransactionMail;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    private static array $sentThisRequest = [];

    public function sendWelcome(User $user): void
    {
        $this->sendOnce($user->email, new RegisterMail($user), [
            'type' => 'welcome',
            'user_id' => $user->id,
        ]);
    }

    public function sendOtp(User $user, string $otp, string $purpose = 'registration'): void
    {
        $this->sendOnce($user->email, new OtpMail($user, $otp, $purpose), [
            'type' => 'otp',
            'purpose' => $purpose,
            'user_id' => $user->id,
        ]);
    }

    public function sendDepositInitiated(Wallet $wallet, Transaction $transaction): void
    {
        $user = $wallet->loadMissing('user')->user;

        if (! $user?->email) {
            return;
        }

        $this->sendOnce($user->email, new DepositInitiatedMail($user, $transaction), [
            'type' => 'deposit_initiated',
            'transaction_id' => $transaction->id,
        ]);
    }

    public function sendTransactionNotice(Wallet $wallet, Transaction $transaction): void
    {
        $user = $wallet->loadMissing('user')->user;

        if (! $user?->email) {
            return;
        }

        $this->sendOnce($user->email, new TransactionMail($user, $transaction), [
            'type' => 'transaction_notice',
            'transaction_id' => $transaction->id,
            'status' => $transaction->status,
        ]);
    }

    public function sendWithdrawalInitiated(Wallet $wallet, Transaction $transaction): void
    {
        $user = $wallet->loadMissing('user')->user;

        if (! $user?->email) {
            return;
        }

        $this->sendOnce($user->email, new TransactionMail($user, $transaction), [
            'type' => 'withdrawal_initiated',
            'transaction_id' => $transaction->id,
        ]);
    }

    public function sendTransferInitiated(Wallet $wallet, Transaction $transaction): void
    {
        $user = $wallet->loadMissing('user')->user;

        if (! $user?->email) {
            return;
        }

        $this->sendOnce($user->email, new TransactionMail($user, $transaction), [
            'type' => 'transfer_initiated',
            'transaction_id' => $transaction->id,
        ]);
    }

    public function sendTransactionCompleted(Wallet $wallet, Transaction $transaction): void
    {
        $user = $wallet->loadMissing('user')->user;

        if (! $user?->email) {
            return;
        }

        $this->sendOnce($user->email, new TransactionMail($user, $transaction), [
            'type' => 'transaction_completed',
            'transaction_id' => $transaction->id,
        ]);
    }

    private function sendOnce(string $recipient, Mailable $mailable, array $context): void
    {
        $key = $recipient . ':' . get_class($mailable) . ':' . md5(json_encode($context));

        if (isset(self::$sentThisRequest[$key])) {
            return;
        }

        self::$sentThisRequest[$key] = true;

        Mail::to($recipient)->send($mailable);
    }
}
