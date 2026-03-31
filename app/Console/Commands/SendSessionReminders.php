<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\MentorSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendSessionReminders extends Command
{
    protected $signature   = 'sessions:send-reminders
                              {--minutes=5 : How many minutes before start_time to send the reminder}
                              {--window=2  : Tolerance window in minutes (catches scheduler drift)}';

    protected $description = 'Send 5-minute "session starting soon" reminders to mentor and all confirmed students.';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $window  = (int) $this->option('window');

        // Find upcoming sessions starting between (minutes - window) and (minutes + window)
        // e.g. between 3 and 7 minutes from now — catches cron drift
        $from = now()->addMinutes($minutes - $window);
        $to   = now()->addMinutes($minutes + $window);

        $sessions = MentorSession::where('status', 'upcoming')
            ->whereNull('reminder_sent_at')          // not yet reminded
            ->whereBetween('start_time', [$from, $to])
            ->with(['mentor', 'bookings.student'])
            ->get();

        foreach ($sessions as $session) {
            $startFormatted = $session->start_time->format('D, d M • h:i A');
            $minsLeft       = (int) round(now()->diffInMinutes($session->start_time, false));
            $minsLabel      = $minsLeft > 0 ? "in {$minsLeft} minutes" : 'now';

            // ── Notify Mentor ──────────────────────────────────────────
            if ($session->mentor) {
                AppNotification::create([
                    'user_id' => $session->mentor_id,
                    'title'   => '⏰ Session Starting Soon',
                    'body'    => "Your session \"{$session->title}\" starts {$minsLabel} ({$startFormatted}). "
                               . ($session->type === 'group'
                                    ? "You have {$session->seats_booked} student(s) enrolled."
                                    : "Your student is waiting."),
                    'type'    => 'session_reminder',
                ]);
            }

            // ── Notify Each Confirmed Student ──────────────────────────
            $confirmedBookings = $session->bookings->where('status', 'confirmed');

            foreach ($confirmedBookings as $booking) {
                if (!$booking->student) continue;

                AppNotification::create([
                    'user_id' => $booking->student_id,
                    'title'   => '⏰ Your Session Starts Soon',
                    'body'    => "Your session \"{$session->title}\" with {$session->mentor?->full_name} "
                               . "starts {$minsLabel} ({$startFormatted}). Get ready to join!",
                    'type'    => 'session_reminder',
                ]);
            }

            // Mark reminder sent so it won't be sent again
            $session->update(['reminder_sent_at' => now()]);

            Log::info("Reminder sent for session #{$session->id} — mentor + {$confirmedBookings->count()} student(s).");
        }

        $count = $sessions->count();
        $this->info("Reminders sent for {$count} session(s).");

        return self::SUCCESS;
    }
}
