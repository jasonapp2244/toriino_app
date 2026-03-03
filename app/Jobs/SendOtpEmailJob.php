<?php

namespace App\Jobs;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOtpEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry up to 3 times if mail server fails.
     */
    public int $tries = 3;

    /**
     * Wait 30 s before each retry.
     */
    public int $backoff = 30;

    public function __construct(
        public readonly User   $user,
        public readonly string $otpCode,
        public readonly bool   $isResend = false
    ) {}

    public function handle(): void
    {
        Mail::to($this->user->email)
            ->send(new OtpMail($this->user, $this->otpCode, $this->isResend));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendOtpEmailJob failed', [
            'user_id' => $this->user->id,
            'email'   => $this->user->email,
            'error'   => $exception->getMessage(),
        ]);
    }
}
