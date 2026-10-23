<?php

namespace App\Mail;

use App\Models\SubMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DepositInitiatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public ?SubMethod $subMethod;

    public function __construct(
        public readonly User        $user,
        public readonly Transaction $transaction,
    ) {
        // Eagerly resolve payment details from sub_method_id
        $this->subMethod = $transaction->sub_method_id
            ? SubMethod::with('method')->find($transaction->sub_method_id)
            : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Deposit Initiated â€” ' . config('platform.company_full_name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.deposit-initiated',
            with: [
                'user'        => $this->user,
                'transaction' => $this->transaction,
                'subMethod'   => $this->subMethod,
                'asset'       => $this->transaction->asset,
                'companyName' => config('platform.company_full_name'),
            ],
        );
    }
}
