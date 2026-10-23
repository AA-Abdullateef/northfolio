<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $customSubject,
        public readonly string $customMessage,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->customSubject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.custom',
            with: [
                'customMessage' => $this->customMessage,
                'companyName'   => config('platform.company_full_name'),
            ],
        );
    }
}
