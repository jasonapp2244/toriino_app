<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\MentorSession;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::where('email', 'student@turiino.com')->first();
        $mentor  = User::where('email', 'mentor@turiino.com')->first();
        $teacher = User::where('email', 'teacher@turiino.com')->first();

        if (!$student || !$mentor || !$teacher) {
            $this->command->warn('PaymentSeeder: required users not found. Run AdminUserSeeder first.');
            return;
        }

        // ─── Payment for completed session ────────────────────────
        $completedSession = MentorSession::where('mentor_id', $mentor->id)
            ->where('status', 'completed')
            ->first();

        if ($completedSession) {
            Payment::firstOrCreate(
                [
                    'user_id'      => $student->id,
                    'payable_type' => 'session',
                    'payable_id'   => $completedSession->id,
                ],
                [
                    'amount'             => $completedSession->price,
                    'currency'           => 'USD',
                    'status'             => 'paid',
                    'payment_method'     => 'card',
                    'transaction_id'     => 'TXN-' . strtoupper(Str::random(10)),
                    'payment_reference'  => 'PAY-' . strtoupper(Str::random(12)),
                    'gateway_response'   => ['status' => 'success', 'gateway' => 'stripe'],
                    'paid_at'            => now()->subDays(5),
                ]
            );
        }

        // ─── Payment for course enrollment ────────────────────────
        $mathCourse = Course::where('teacher_id', $teacher->id)
            ->where('title', 'Complete Mathematics for Beginners')
            ->first();

        if ($mathCourse) {
            Payment::firstOrCreate(
                [
                    'user_id'      => $student->id,
                    'payable_type' => 'course',
                    'payable_id'   => $mathCourse->id,
                ],
                [
                    'amount'             => $mathCourse->price,
                    'currency'           => 'USD',
                    'status'             => 'paid',
                    'payment_method'     => 'card',
                    'transaction_id'     => 'TXN-' . strtoupper(Str::random(10)),
                    'payment_reference'  => 'PAY-' . strtoupper(Str::random(12)),
                    'gateway_response'   => ['status' => 'success', 'gateway' => 'stripe'],
                    'paid_at'            => now()->subDays(4),
                ]
            );
        }

        $this->command->info('✅ PaymentSeeder: 2 payment records created (session booking + course enrollment).');
    }
}
