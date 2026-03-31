<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        $mentor  = User::where('email', 'mentor@turiino.com')->first();
        $teacher = User::where('email', 'teacher@turiino.com')->first();
        $student = User::where('email', 'student@turiino.com')->first();

        if (!$mentor || !$teacher || !$student) {
            $this->command->warn('ConversationSeeder: required users not found. Run AdminUserSeeder first.');
            return;
        }

        // ─── Student ↔ Mentor conversation ───────────────────────
        $convStudentMentor = Conversation::between($student->id, $mentor->id);
        $convStudentMentor->update(['last_message_at' => now()->subMinutes(10)]);

        $studentMentorMessages = [
            [$mentor->id,  'Hi there! Glad to connect. Let me know if you have any questions about the upcoming session.', now()->subHours(3)],
            [$student->id, 'Thanks! I wanted to ask — what topics will you cover in the Laravel API session?', now()->subHours(2)->subMinutes(45)],
            [$mentor->id,  'We will cover RESTful API design, authentication with Sanctum, resource controllers, and API testing with Postman.', now()->subHours(2)->subMinutes(30)],
            [$student->id, 'Perfect, that is exactly what I needed. Should I prepare anything beforehand?', now()->subHours(2)],
            [$mentor->id,  'Yes — install Postman and make sure you have a fresh Laravel project set up. I will share a starter repo link before the session.', now()->subMinutes(10)],
        ];

        foreach ($studentMentorMessages as [$senderId, $message, $createdAt]) {
            $exists = ConversationMessage::where('conversation_id', $convStudentMentor->id)
                ->where('sender_id', $senderId)
                ->where('message', $message)
                ->exists();

            if (!$exists) {
                ConversationMessage::create([
                    'conversation_id' => $convStudentMentor->id,
                    'sender_id'       => $senderId,
                    'message'         => $message,
                    'type'            => 'text',
                    'is_read'         => $senderId === $mentor->id,
                    'created_at'      => $createdAt,
                    'updated_at'      => $createdAt,
                ]);
            }
        }

        // ─── Student ↔ Teacher conversation ──────────────────────
        $convStudentTeacher = Conversation::between($student->id, $teacher->id);
        $convStudentTeacher->update(['last_message_at' => now()->subHours(1)]);

        $studentTeacherMessages = [
            [$student->id, 'Hello, I just enrolled in your Mathematics course. Looking forward to it!', now()->subDays(2)],
            [$teacher->id, 'Welcome! Feel free to ask me questions anytime through the course platform.', now()->subDays(2)->addHours(1)],
            [$student->id, 'Quick question — do you have any recommended books for calculus?', now()->subHours(2)],
            [$teacher->id, 'I recommend "Calculus" by James Stewart for beginners. The course covers the same curriculum so you should do fine without it.', now()->subHours(1)],
        ];

        foreach ($studentTeacherMessages as [$senderId, $message, $createdAt]) {
            $exists = ConversationMessage::where('conversation_id', $convStudentTeacher->id)
                ->where('sender_id', $senderId)
                ->where('message', $message)
                ->exists();

            if (!$exists) {
                ConversationMessage::create([
                    'conversation_id' => $convStudentTeacher->id,
                    'sender_id'       => $senderId,
                    'message'         => $message,
                    'type'            => 'text',
                    'is_read'         => $senderId === $teacher->id,
                    'created_at'      => $createdAt,
                    'updated_at'      => $createdAt,
                ]);
            }
        }

        $this->command->info('✅ ConversationSeeder: 2 conversations created (student↔mentor: 5 msgs, student↔teacher: 4 msgs).');
    }
}
