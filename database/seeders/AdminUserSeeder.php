<?php

namespace Database\Seeders;

use App\Models\MentorProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Demo Mentor ─────────────────────────────────────────
        $mentor = User::updateOrCreate(
            ['email' => 'mentor@turiino.com'],
            [
                'name'               => 'Demo Mentor',
                'full_name'          => 'Demo Mentor',
                'phone'              => '+1234567890',
                'password'           => Hash::make('password'),
                'role'               => 'mentor',
                'is_verified'        => true,
                'status'             => 'active',
                'email_verified_at'  => now(),
                'language'           => 'en',
                'timezone'           => 'America/New_York',
                'last_active_at'     => now(),
            ]
        );

        if ($mentor->getRoleNames()->isEmpty()) {
            $mentor->assignRole('mentor');
        }

        $mentor->mentorProfile()->updateOrCreate(
            ['user_id' => $mentor->id],
            [
                'designation'             => 'Senior Full-Stack Developer',
                'short_bio'               => 'Experienced full-stack developer with 8 years building web & mobile apps.',
                'specialization'          => 'Web Development',
                'industry'                => 'Technology',
                'intro'                   => 'Experienced full-stack developer with 8 years of experience.',
                'price_per_hour'          => 50.00,
                'expertise_list'          => ['Laravel', 'Vue.js', 'React Native', 'MySQL', 'AWS'],
                'preferred_student_level' => 'intermediate',
                'languages'               => 'English, Spanish',
                'languages_list'          => ['English', 'Spanish'],
                'experience_years'        => '8',
                'is_verified'             => true,
                'is_featured'             => true,
                'rating'                  => 4.8,
                'total_reviews'           => 24,
                'profile_setup_complete'  => true,
            ]
        );

        // ─── Demo Teacher ─────────────────────────────────────────
        $teacher = User::updateOrCreate(
            ['email' => 'teacher@turiino.com'],
            [
                'name'               => 'Demo Teacher',
                'full_name'          => 'Demo Teacher',
                'phone'              => '+1234567891',
                'password'           => Hash::make('password'),
                'role'               => 'teacher',
                'is_verified'        => true,
                'status'             => 'active',
                'email_verified_at'  => now(),
                'language'           => 'en',
                'timezone'           => 'Europe/London',
                'last_active_at'     => now(),
            ]
        );

        if ($teacher->getRoleNames()->isEmpty()) {
            $teacher->assignRole('teacher');
        }

        $teacher->teacherProfile()->updateOrCreate(
            ['user_id' => $teacher->id],
            [
                'designation'            => 'Mathematics Lecturer',
                'subject'                => 'Mathematics',
                'subject_field'          => 'Algebra, Calculus & Geometry',
                'short_bio'              => 'MSc Mathematics graduate with 5 years of university-level teaching experience.',
                'degree'                 => 'MSc Mathematics',
                'experience_years'       => '5',
                'expertise_list'         => ['Algebra', 'Calculus', 'Geometry', 'Statistics'],
                'languages'              => 'English',
                'languages_list'         => ['English'],
                'is_verified'            => true,
                'rating'                 => 4.6,
                'total_reviews'          => 18,
                'profile_setup_complete' => true,
            ]
        );

        // ─── Demo Student ─────────────────────────────────────────
        $student = User::updateOrCreate(
            ['email' => 'student@turiino.com'],
            [
                'name'               => 'Demo Student',
                'full_name'          => 'Demo Student',
                'phone'              => '+1234567892',
                'password'           => Hash::make('password'),
                'role'               => 'student',
                'is_verified'        => true,
                'status'             => 'active',
                'email_verified_at'  => now(),
                'language'           => 'en',
                'timezone'           => 'Asia/Dubai',
                'last_active_at'     => now(),
            ]
        );

        if ($student->getRoleNames()->isEmpty()) {
            $student->assignRole('student');
        }

        $student->studentProfile()->updateOrCreate(
            ['user_id' => $student->id],
            [
                'student_id'             => 'STU-' . str_pad($student->id, 6, '0', STR_PAD_LEFT),
                'bio'                    => 'Passionate about learning web development and data science.',
                'language'               => 'English',
                'interests'              => 'Web Development, Data Science, Mobile Apps',
                'education_level'        => 'Bachelor of Computer Science',
                'courses_completed'      => 0,
                'certificates_earned'    => 0,
                'profile_setup_complete' => true,
            ]
        );

        $this->command->info('Demo users seeded: mentor@turiino.com | teacher@turiino.com | student@turiino.com  (password: password)');
    }
}
