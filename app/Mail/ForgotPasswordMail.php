<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly string $otpCode
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your Turiino password');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.forgot-password',
            with: [
                'userName'    => $this->user->full_name ?? $this->user->name,
                'userEmail'   => $this->user->email,
                'otpCode'     => $this->otpCode,
                'requestedAt' => now()->format('d M Y, h:i A') . ' UTC',
                'emailTitle'  => 'Password Reset — Turiino',
            ]
        );
    }
}
