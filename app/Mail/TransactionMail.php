<?php

namespace App\Mail;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User        $user,
        public readonly Transaction $transaction,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Transaction Update â€” ' . config('platform.company_full_name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.transaction',
            with: [
                'user'        => $this->user,
                'transaction' => $this->transaction->loadMissing('asset'),
                'companyName' => config('platform.company_full_name'),
            ],
        );
    }
}
