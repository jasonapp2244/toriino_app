<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly string $otpCode,
        public readonly bool   $isResend = false
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isResend
            ? 'Your new Turiino verification code'
            : 'Verify your Turiino account';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.otp',
            with: [
                'userName'  => $this->user->full_name ?? $this->user->name,
                'userEmail' => $this->user->email,
                'otpCode'   => $this->otpCode,
                'isResend'  => $this->isResend,
                'emailTitle'=> $this->isResend
                                 ? 'Your new verification code'
                                 : 'Verify your email — Turiino',
            ]
        );
    }
}
