<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonVideoProgress;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseEnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::where('email', 'student@turiino.com')->first();
        $teacher = User::where('email', 'teacher@turiino.com')->first();

        if (!$student || !$teacher) {
            $this->command->warn('CourseEnrollmentSeeder: required users not found. Run AdminUserSeeder first.');
            return;
        }

        $mathCourse = Course::where('teacher_id', $teacher->id)
            ->where('title', 'Complete Mathematics for Beginners')
            ->first();

        if (!$mathCourse) {
            $this->command->warn('CourseEnrollmentSeeder: math course not found. Run SampleDataSeeder first.');
            return;
        }

        // Enroll student in math course with partial progress
        $enrollment = CourseEnrollment::firstOrCreate(
            ['course_id' => $mathCourse->id, 'student_id' => $student->id],
            [
                'amount_paid'      => $mathCourse->price,
                'progress_percent' => 33,
                'status'           => 'active',
            ]
        );

        // Seed lesson video progress for first 2 lessons (completed) and 3rd (in progress)
        $lessons = Lesson::where('course_id', $mathCourse->id)->orderBy('order')->get();

        foreach ($lessons as $index => $lesson) {
            if ($index === 0) {
                // First lesson: fully watched
                LessonVideoProgress::firstOrCreate(
                    ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id],
                    [
                        'watched_seconds'       => 2700,
                        'total_seconds'         => 2700,
                        'percentage_watched'    => 100.0,
                        'last_position_seconds' => 2700,
                        'is_completed'          => true,
                        'completed_at'          => now()->subDays(3),
                    ]
                );
            } elseif ($index === 1) {
                // Second lesson: fully watched
                LessonVideoProgress::firstOrCreate(
                    ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id],
                    [
                        'watched_seconds'       => 3600,
                        'total_seconds'         => 3600,
                        'percentage_watched'    => 100.0,
                        'last_position_seconds' => 3600,
                        'is_completed'          => true,
                        'completed_at'          => now()->subDays(2),
                    ]
                );
            } elseif ($index === 2) {
                // Third lesson: partially watched
                LessonVideoProgress::firstOrCreate(
                    ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id],
                    [
                        'watched_seconds'       => 1200,
                        'total_seconds'         => 3300,
                        'percentage_watched'    => 36.4,
                        'last_position_seconds' => 1200,
                        'is_completed'          => false,
                        'completed_at'          => null,
                    ]
                );
            }
        }

        $this->command->info('✅ CourseEnrollmentSeeder: enrollment + lesson video progress created.');
    }
}
