<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Earning;
use App\Models\MentorSession;

class EarningService
{
    /**
     * Platform commission rate (20%).
     * Loaded from config, with 0.20 as fallback.
     */
    private function commissionRate(): float
    {
        return (float) config('services.platform.commission_rate', 0.20);
    }

    /**
     * Create an earning for a course enrollment (idempotent via firstOrCreate).
     */
    public function createForCourse(int $teacherId, Course $course): Earning
    {
        $commission = $course->price * $this->commissionRate();
        $teacherCut = $course->price - $commission;

        return Earning::firstOrCreate(
            [
                'user_id'        => $teacherId,
                'reference_id'   => $course->id,
                'reference_type' => 'Course',
                'type'           => 'course',
            ],
            [
                'amount'      => $teacherCut,
                'description' => 'Enrollment: ' . $course->title,
            ]
        );
    }

    /**
     * Create an earning for a session booking (idempotent via firstOrCreate).
     */
    public function createForSession(int $mentorId, MentorSession $session): Earning
    {
        $commission = $session->price * $this->commissionRate();
        $mentorCut  = $session->price - $commission;

        return Earning::firstOrCreate(
            [
                'user_id'        => $mentorId,
                'reference_id'   => $session->id,
                'reference_type' => 'MentorSession',
                'type'           => 'session',
            ],
            [
                'amount'      => $mentorCut,
                'description' => 'Session: ' . $session->title,
            ]
        );
    }
}
