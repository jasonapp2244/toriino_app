<?php

namespace App\Console\Commands;

use App\Models\MentorSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCompleteExpiredSessions extends Command
{
    protected $signature = 'sessions:auto-complete
                            {--grace=10 : Grace period in minutes after end_time before force-completing}';

    protected $description = 'Auto-complete ongoing sessions that have passed their end_time + grace period, and cancel upcoming sessions that started but were never started within their window.';

    public function handle(): int
    {
        $graceMins = (int) $this->option('grace');

        // ── 1. Force-complete sessions that are ONGOING and past end_time + grace ─
        $expiredOngoing = MentorSession::where('status', 'ongoing')
            ->where('end_time', '<', now()->subMinutes($graceMins))
            ->get();

        // ── 1b. Force-complete sessions where mentor stopped pinging > 10 min ago ─
        // (mentor phone died / network lost / app crashed)
        $orphanedByPing = MentorSession::where('status', 'ongoing')
            ->whereNotNull('mentor_last_ping_at')
            ->where('mentor_last_ping_at', '<', now()->subMinutes(10))
            ->whereNotIn('id', $expiredOngoing->pluck('id'))
            ->get();

        $expiredOngoing = $expiredOngoing->merge($orphanedByPing);

        foreach ($expiredOngoing as $session) {
            $session->update(['status' => 'completed']);

            // Record earnings for every confirmed booking
            foreach ($session->bookings()->where('status', 'confirmed')->get() as $booking) {
                $session->mentor->earnings()->create([
                    'amount'         => $session->price,
                    'type'           => 'session',
                    'description'    => '[Auto-completed] Session: ' . $session->title,
                    'reference_id'   => $session->id,
                    'reference_type' => 'MentorSession',
                ]);
            }

            Log::info("AutoComplete: session #{$session->id} force-completed (past end_time + {$graceMins}min grace).");
        }

        // ── 2. Cancel upcoming sessions whose start_time is > 30 min in the past ─
        // These were never started by the mentor; mark them cancelled so students
        // are not left waiting indefinitely.
        $abandonedUpcoming = MentorSession::where('status', 'upcoming')
            ->where('start_time', '<', now()->subMinutes(30))
            ->get();

        foreach ($abandonedUpcoming as $session) {
            $session->update(['status' => 'cancelled']);
            Log::info("AutoComplete: session #{$session->id} auto-cancelled (mentor never started, 30min passed).");
        }

        $totalFixed = $expiredOngoing->count() + $abandonedUpcoming->count();
        $this->info("Done. Completed: {$expiredOngoing->count()}, Cancelled: {$abandonedUpcoming->count()}.");

        return self::SUCCESS;
    }
}
