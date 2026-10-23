<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegisterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . config('platform.company_full_name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.register',
            with: [
                'user'        => $this->user,
                'companyName' => config('platform.company_full_name'),
                'companyUrl'  => config('platform.company_url'),
            ],
        );
    }
}
