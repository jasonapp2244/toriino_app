<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Earning;
use App\Models\MentorSession;
use App\Models\SessionBooking;
use App\Models\User;
use Illuminate\Database\Seeder;

class EarningSeeder extends Seeder
{
    public function run(): void
    {
        $mentor  = User::where('email', 'mentor@turiino.com')->first();
        $teacher = User::where('email', 'teacher@turiino.com')->first();
        $student = User::where('email', 'student@turiino.com')->first();

        if (!$mentor || !$teacher || !$student) {
            $this->command->warn('EarningSeeder: required users not found. Run AdminUserSeeder first.');
            return;
        }

        // ─── Mentor earnings from completed session ───────────────
        $completedSession = MentorSession::where('mentor_id', $mentor->id)
            ->where('status', 'completed')
            ->first();

        if ($completedSession) {
            $booking = SessionBooking::where('session_id', $completedSession->id)
                ->where('student_id', $student->id)
                ->first();

            $netAmount = round($completedSession->price * 0.85, 2);

            Earning::firstOrCreate(
                [
                    'user_id'        => $mentor->id,
                    'reference_type' => 'session_booking',
                    'reference_id'   => $booking?->id ?? $completedSession->id,
                ],
                [
                    'amount'      => $netAmount,
                    'type'        => 'session',
                    'description' => 'Earning from: ' . $completedSession->title,
                ]
            );
        }

        // ─── Teacher earnings from course enrollment ──────────────
        $mathCourse = Course::where('teacher_id', $teacher->id)
            ->where('title', 'Complete Mathematics for Beginners')
            ->first();

        if ($mathCourse) {
            $enrollment = CourseEnrollment::where('course_id', $mathCourse->id)
                ->where('student_id', $student->id)
                ->first();

            $netAmount = round($mathCourse->price - $mathCourse->platform_fee, 2);

            Earning::firstOrCreate(
                [
                    'user_id'        => $teacher->id,
                    'reference_type' => 'course_enrollment',
                    'reference_id'   => $enrollment?->id ?? $mathCourse->id,
                ],
                [
                    'amount'      => $netAmount,
                    'type'        => 'course',
                    'description' => 'Enrollment earning: ' . $mathCourse->title,
                ]
            );
        }

        // ─── Mentor additional historical session earnings ────────
        $historicalEarnings = [
            ['amount' => 42.50, 'description' => 'JavaScript Deep Dive session'],
            ['amount' => 63.75, 'description' => 'System Design Workshop'],
            ['amount' => 21.25, 'description' => 'Git & GitHub for Teams'],
        ];

        foreach ($historicalEarnings as $index => $e) {
            Earning::firstOrCreate(
                [
                    'user_id'     => $mentor->id,
                    'description' => $e['description'],
                ],
                [
                    'amount'         => $e['amount'],
                    'type'           => 'session',
                    'reference_type' => 'historical',
                    'reference_id'   => null,
                ]
            );
        }

        $this->command->info('✅ EarningSeeder: mentor (1 session + 3 historical) + teacher (1 course) earnings created.');
    }
}
