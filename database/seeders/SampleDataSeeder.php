<?php

namespace Database\Seeders;

use App\Models\AiChat;
use App\Models\AppNotification;
use App\Models\Availability;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MentorSession;
use App\Models\PrivacyPolicy;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $mentor  = User::where('email', 'mentor@turiino.com')->first();
        $teacher = User::where('email', 'teacher@turiino.com')->first();
        $student = User::where('email', 'student@turiino.com')->first();

        if (!$mentor || !$teacher || !$student) {
            $this->command->warn('Run AdminUserSeeder first.');
            return;
        }

        // ─── Mentor Active Subscription ──────────────────────────
        Subscription::updateOrCreate(
            ['user_id' => $mentor->id, 'status' => 'active'],
            [
                'plan'       => 'monthly',
                'price'      => 9.99,
                'starts_at'  => now(),
                'expires_at' => now()->addDays(30),
                'status'     => 'active',
            ]
        );

        // ─── Teacher Active Subscription ─────────────────────────
        Subscription::updateOrCreate(
            ['user_id' => $teacher->id, 'status' => 'active'],
            [
                'plan'       => 'monthly',
                'price'      => 9.99,
                'starts_at'  => now(),
                'expires_at' => now()->addDays(30),
                'status'     => 'active',
            ]
        );

        // ─── Mentor Availability ─────────────────────────────────
        $slots = [
            'Monday'    => ['09:00', '17:00'],
            'Tuesday'   => ['09:00', '17:00'],
            'Wednesday' => ['10:00', '15:00'],
            'Thursday'  => ['09:00', '17:00'],
            'Friday'    => ['09:00', '13:00'],
        ];
        foreach ($slots as $day => [$start, $end]) {
            Availability::updateOrCreate(
                ['mentor_id' => $mentor->id, 'day' => $day],
                ['start_time' => $start, 'end_time' => $end, 'is_available' => true]
            );
        }

        // ─── Mentor Sessions ─────────────────────────────────────
        $sessions = [
            [
                'title'            => 'Laravel API Development — Live Session',
                'type'             => 'group',
                'start_time'       => now()->addDays(2)->setHour(10)->setMinute(0)->setSecond(0),
                'end_time'         => now()->addDays(2)->setHour(11)->setMinute(0)->setSecond(0),
                'duration_minutes' => 60,
                'max_seats'        => 10,
                'language'         => 'English',
                'price'            => 25.00,
                'status'           => 'upcoming',
            ],
            [
                'title'            => 'Vue.js for Beginners — 1:1 Session',
                'type'             => 'individual',
                'start_time'       => now()->addDays(4)->setHour(14)->setMinute(0)->setSecond(0),
                'end_time'         => now()->addDays(4)->setHour(15)->setMinute(0)->setSecond(0),
                'duration_minutes' => 60,
                'max_seats'        => 1,
                'language'         => 'English',
                'price'            => 50.00,
                'status'           => 'upcoming',
            ],
            [
                'title'            => 'Career Mentoring — Q&A Session',
                'type'             => 'group',
                'start_time'       => now()->subDays(5)->setHour(10)->setMinute(0)->setSecond(0),
                'end_time'         => now()->subDays(5)->setHour(11)->setMinute(0)->setSecond(0),
                'duration_minutes' => 60,
                'max_seats'        => 15,
                'language'         => 'English',
                'price'            => 15.00,
                'status'           => 'completed',
            ],
        ];

        foreach ($sessions as $s) {
            MentorSession::firstOrCreate(
                ['mentor_id' => $mentor->id, 'title' => $s['title']],
                $s
            );
        }

        // ─── Course ──────────────────────────────────────────────
        $course = Course::updateOrCreate(
            ['teacher_id' => $teacher->id, 'title' => 'Complete Mathematics for Beginners'],
            [
                'description'       => 'A comprehensive course covering algebra, geometry, and calculus from scratch.',
                'category'          => 'Mathematics',
                'language'          => 'English',
                'duration'          => '12 hours',
                'price'             => 49.99,
                'platform_fee'      => 5.00,
                'status'            => 'published',
                'total_enrollments' => 3,
                'rating'            => 4.7,
            ]
        );

        $lessons = [
            ['title' => 'Introduction to Algebra',          'order' => 1, 'is_free' => true,  'duration' => '45 min'],
            ['title' => 'Solving Linear Equations',         'order' => 2, 'is_free' => false, 'duration' => '60 min'],
            ['title' => 'Quadratic Equations Explained',    'order' => 3, 'is_free' => false, 'duration' => '55 min'],
            ['title' => 'Introduction to Geometry',         'order' => 4, 'is_free' => false, 'duration' => '50 min'],
            ['title' => 'Trigonometry Fundamentals',        'order' => 5, 'is_free' => false, 'duration' => '60 min'],
            ['title' => 'Calculus: Limits & Derivatives',   'order' => 6, 'is_free' => false, 'duration' => '65 min'],
        ];

        foreach ($lessons as $lesson) {
            Lesson::firstOrCreate(
                ['course_id' => $course->id, 'title' => $lesson['title']],
                [
                    'video_url' => 'https://example.com/videos/' . \Str::slug($lesson['title']),
                    'order'     => $lesson['order'],
                    'is_free'   => $lesson['is_free'],
                    'duration'  => $lesson['duration'],
                ]
            );
        }

        // ─── Reviews ─────────────────────────────────────────────
        $session = MentorSession::where('mentor_id', $mentor->id)->where('status', 'completed')->first();

        if ($session) {
            Review::firstOrCreate(
                ['from_user_id' => $student->id, 'session_id' => $session->id],
                [
                    'to_user_id' => $mentor->id,
                    'rating'     => 5,
                    'comment'    => 'Excellent session! Very clear explanations and very patient.',
                ]
            );
        }

        Review::firstOrCreate(
            ['from_user_id' => $student->id, 'course_id' => $course->id],
            [
                'to_user_id' => $teacher->id,
                'rating'     => 4,
                'comment'    => 'Great course content, well structured and easy to follow.',
            ]
        );

        // ─── Notifications ───────────────────────────────────────
        $notifications = [
            ['title' => 'Welcome to Turiino! 🎉',         'body' => 'Your account has been verified. Start exploring mentors and courses!', 'type' => 'welcome'],
            ['title' => 'New session available',            'body' => 'Demo Mentor has added a new Laravel session. Book now!', 'type' => 'session'],
            ['title' => 'Course recommendation',            'body' => 'Check out the Complete Mathematics course — 5 star rated!', 'type' => 'course'],
            ['title' => 'Limited seats available',         'body' => 'Only 3 seats left for Vue.js for Beginners session.', 'type' => 'alert'],
        ];

        foreach ($notifications as $n) {
            AppNotification::firstOrCreate(
                ['user_id' => $student->id, 'title' => $n['title']],
                ['body' => $n['body'], 'type' => $n['type']]
            );
        }

        // Notification for mentor
        AppNotification::firstOrCreate(
            ['user_id' => $mentor->id, 'title' => 'New booking request'],
            ['body' => 'A student has booked your Laravel API session.', 'type' => 'booking']
        );

        // ─── Sample AI Chat ──────────────────────────────────────
        $aiChats = [
            [
                'question' => 'What is the Pythagorean theorem?',
                'answer'   => 'The Pythagorean theorem states that in a right triangle, the square of the hypotenuse equals the sum of squares of the other two sides: a² + b² = c².',
            ],
            [
                'question' => 'Explain recursion in programming.',
                'answer'   => 'Recursion is a technique where a function calls itself to solve smaller instances of the same problem. It needs a base case to stop and a recursive case to continue. Example: factorial(n) = n * factorial(n-1), with factorial(0) = 1 as the base case.',
            ],
        ];

        foreach ($aiChats as $chat) {
            AiChat::firstOrCreate(
                ['user_id' => $student->id, 'question' => $chat['question']],
                ['answer' => $chat['answer']]
            );
        }

        // ─── Privacy Policy ──────────────────────────────────────
        PrivacyPolicy::updateOrCreate(
            ['title' => 'Privacy Policy'],
            [
                'content' => "# Turiino Privacy Policy\n\nLast updated: February 2026\n\n"
                    . "## 1. Data Collection\nWe collect your name, email, phone number, and usage data to provide our services.\n\n"
                    . "## 2. Data Usage\nYour data is used only for service delivery and is never sold to third parties.\n\n"
                    . "## 3. Cookies\nWe use cookies to maintain session state and improve user experience.\n\n"
                    . "## 4. Third-Party Services\nWe integrate with OpenAI for AI tutoring. Your questions are processed by OpenAI's API.\n\n"
                    . "## 5. Your Rights\nYou may request deletion of your account and data at any time.\n\n"
                    . "## 6. Contact\nFor privacy concerns: privacy@turiino.com",
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Sample data seeded successfully.');
        $this->command->info('   - 2 subscriptions (mentor + teacher)');
        $this->command->info('   - 5 availability slots for mentor');
        $this->command->info('   - 3 mentor sessions (2 upcoming, 1 completed)');
        $this->command->info('   - 1 course with 6 lessons');
        $this->command->info('   - 2 reviews, 5 notifications, 2 AI chats');
    }
}
