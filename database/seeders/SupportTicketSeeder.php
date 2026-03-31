<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupportTicketSeeder extends Seeder
{
    public function run(): void
    {
        $mentor  = User::where('email', 'mentor@turiino.com')->first();
        $teacher = User::where('email', 'teacher@turiino.com')->first();
        $student = User::where('email', 'student@turiino.com')->first();

        if (!$mentor || !$teacher || !$student) {
            $this->command->warn('SupportTicketSeeder: required users not found. Run AdminUserSeeder first.');
            return;
        }

        $tickets = [
            // Student tickets
            [
                'user_id' => $student->id,
                'subject' => 'Cannot access enrolled course videos',
                'message' => 'I enrolled in the Mathematics course but the lesson videos are not loading. I get a blank screen after clicking on lesson 3.',
                'status'  => 'open',
                'admin_reply' => null,
            ],
            [
                'user_id' => $student->id,
                'subject' => 'Payment deducted but booking not confirmed',
                'message' => 'I paid for the Laravel API session but my booking page still shows pending. Please help.',
                'status'  => 'resolved',
                'admin_reply' => 'We have investigated and your booking is confirmed. Please refresh the app. We apologise for the inconvenience.',
            ],
            // Mentor tickets
            [
                'user_id' => $mentor->id,
                'subject' => 'Withdrawal request pending for over 7 days',
                'message' => 'I submitted a withdrawal request 7 days ago but it is still showing as pending. Can you provide an update?',
                'status'  => 'open',
                'admin_reply' => null,
            ],
            [
                'user_id' => $mentor->id,
                'subject' => 'Session join link not working',
                'message' => 'Students are reporting that the join link for my upcoming Laravel session is returning a 404 error.',
                'status'  => 'in_progress',
                'admin_reply' => 'We are investigating this issue with our video provider. A fix will be deployed within 2 hours.',
            ],
            // Teacher tickets
            [
                'user_id' => $teacher->id,
                'subject' => 'Course thumbnail upload failing',
                'message' => 'Every time I try to upload a thumbnail image for my Web Development course it fails with a server error.',
                'status'  => 'open',
                'admin_reply' => null,
            ],
        ];

        foreach ($tickets as $ticket) {
            SupportTicket::firstOrCreate(
                ['user_id' => $ticket['user_id'], 'subject' => $ticket['subject']],
                [
                    'message'     => $ticket['message'],
                    'status'      => $ticket['status'],
                    'admin_reply' => $ticket['admin_reply'],
                ]
            );
        }

        $this->command->info('✅ SupportTicketSeeder: 5 support tickets created (student: 2, mentor: 2, teacher: 1).');
    }
}
