<?php

namespace Database\Seeders;

use App\Models\MentorSession;
use App\Models\SessionBooking;
use App\Models\User;
use Illuminate\Database\Seeder;

class SessionBookingSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::where('email', 'student@turiino.com')->first();
        $mentor  = User::where('email', 'mentor@turiino.com')->first();

        if (!$student || !$mentor) {
            $this->command->warn('SessionBookingSeeder: required users not found. Run AdminUserSeeder first.');
            return;
        }

        // Book the completed session (past — for review/earnings testing)
        $completedSession = MentorSession::where('mentor_id', $mentor->id)
            ->where('status', 'completed')
            ->first();

        if ($completedSession) {
            $booking = SessionBooking::firstOrCreate(
                ['session_id' => $completedSession->id, 'student_id' => $student->id],
                ['status' => 'completed']
            );

            // Reflect seat count
            $completedSession->increment('seats_booked');
        }

        // Book one upcoming group session
        $upcomingGroup = MentorSession::where('mentor_id', $mentor->id)
            ->where('status', 'upcoming')
            ->where('type', 'group')
            ->first();

        if ($upcomingGroup) {
            SessionBooking::firstOrCreate(
                ['session_id' => $upcomingGroup->id, 'student_id' => $student->id],
                ['status' => 'confirmed']
            );

            $upcomingGroup->increment('seats_booked');
        }

        $this->command->info('✅ SessionBookingSeeder: session bookings created.');
    }
}
