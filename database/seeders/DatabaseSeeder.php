<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ── Lookup / reference tables ──────────────────────────
            RoleSeeder::class,
            CourseCategorySeeder::class,
            CourseLevelSeeder::class,
            IndustrySeeder::class,
            AppLanguageSeeder::class,

            // ── Core users + profiles ──────────────────────────────
            AdminUserSeeder::class,

            // ── Sample content (sessions, courses, reviews, etc.) ──
            SampleDataSeeder::class,

            // ── Transactional data (depends on sessions + courses) ─
            SessionBookingSeeder::class,
            CourseEnrollmentSeeder::class,

            // ── Financial data (depends on bookings + enrollments) ─
            PaymentSeeder::class,
            EarningSeeder::class,
            WithdrawalSeeder::class,

            // ── User-generated content ─────────────────────────────
            SupportTicketSeeder::class,
            ConversationSeeder::class,
        ]);
    }
}
