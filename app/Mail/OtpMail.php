<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $heading;
    public string $intro;
    public int    $expiryMinutes;

    public function __construct(
        public readonly User   $user,
        public readonly string $otp,
        public readonly string $purpose = 'registration',
    ) {
        [$this->subjectLine, $this->heading, $this->intro, $this->expiryMinutes] = match ($purpose) {
            'registration' => [
                'Verify your email address',
                'Email Verification',
                'Use the code below to verify your email address.',
                10,
            ],
            'password_reset' => [
                'Password reset code',
                'Password Reset',
                'Use the code below to reset your password.',
                10,
            ],
            'withdrawal' => [
                'Withdrawal confirmation code',
                'Confirm Withdrawal',
                'Use the code below to confirm your withdrawal request.',
                10,
            ],
            'transfer' => [
                'Transfer confirmation code',
                'Confirm Transfer',
                'Use the code below to confirm your transfer request.',
                10,
            ],
            'deactivation' => [
                'Account deactivation code',
                'Confirm Account Deactivation',
                'Use the code below to confirm deactivation of your account.',
                10,
            ],
            default => [
                'Your verification code',
                'Verification Code',
                'Use the code below to complete your request.',
                10,
            ],
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.otp',
            with: [
                'user'          => $this->user,
                'otp'           => $this->otp,
                'heading'       => $this->heading,
                'intro'         => $this->intro,
                'expiryMinutes' => $this->expiryMinutes,
                'companyName'   => config('platform.company_full_name'),
            ],
        );
    }
}
