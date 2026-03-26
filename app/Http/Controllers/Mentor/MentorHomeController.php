<?php

namespace App\Http\Controllers\Mentor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Earning;
use App\Models\MentorSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorHomeController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalSessions    = MentorSession::where('mentor_id', $user->id)->count();
        $upcomingCount    = MentorSession::where('mentor_id', $user->id)
            ->where('status', 'upcoming')
            ->count();
        $avgRating        = $user->reviewsReceived()->avg('rating') ?? 0;
        $totalEarnings    = Earning::where('user_id', $user->id)->sum('amount');

        $upcomingSessions = MentorSession::where('mentor_id', $user->id)
            ->where('status', 'upcoming')
            ->with(['bookings.student'])
            ->orderBy('start_time')
            ->take(5)
            ->get()
            ->map(fn($s) => $this->formatSession($s));

        $recentHistory = MentorSession::where('mentor_id', $user->id)
            ->where('status', 'completed')
            ->with(['bookings.student'])
            ->orderByDesc('end_time')
            ->take(10)
            ->get()
            ->map(fn($s) => $this->formatSession($s));

        return ApiResponse::success([
            'stats' => [
                'total_sessions'    => $totalSessions,
                'upcoming_sessions' => $upcomingCount,
                'average_rating'    => round($avgRating, 1),
                'total_earnings'    => round($totalEarnings, 2),
            ],
            'upcoming_sessions' => $upcomingSessions,
            'recent_history'    => $recentHistory,
        ]);
    }

    private function formatSession(MentorSession $session): array
    {
        $firstStudent   = $session->bookings->first()?->student;
        $startTime      = Carbon::parse($session->start_time);
        $endTime        = Carbon::parse($session->end_time);
        $maxSeats       = $session->group_max_seats ?? $session->max_seats;
        $seatsLeft      = max(0, $maxSeats - $session->seats_booked);

        return [
            'id'             => $session->id,
            'title'          => $session->title,
            'type'           => ucfirst($session->type),
            'status'         => $session->status,
            'start_time'     => $session->start_time,
            'end_time'       => $session->end_time,
            'formatted_date' => $startTime->format('j M, g:i A') . ' – ' . $endTime->format('g:i A'),
            'duration_label' => $this->formatDuration($session->duration_minutes),
            'seats_left'     => $seatsLeft,
            'max_seats'      => $maxSeats,
            'language'       => $session->language,
            'price'          => (float) $session->price,
            'student_name'   => $firstStudent?->full_name ?? 'Student',
            'student_photo'  => $firstStudent?->photo_url,
            'bookings_count' => $session->bookings->count(),
        ];
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) return "{$minutes}min";
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $m > 0 ? "{$h}hr {$m}min" : "{$h}hr";
    }
}
