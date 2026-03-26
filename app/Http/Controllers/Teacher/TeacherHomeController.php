<?php

namespace App\Http\Controllers\Teacher;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Earning;
use App\Models\MentorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TeacherHomeController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user    = $request->user()->load(['teacherProfile', 'profile']);
        $userId  = $user->id;

        // ── Stats ────────────────────────────────────────────────
        $publishedCourses = Course::where('teacher_id', $userId)
            ->where('status', 'published')
            ->count();

        $totalEnrollments = CourseEnrollment::whereHas(
            'course', fn($q) => $q->where('teacher_id', $userId)
        )->count();

        // Average rating from TeacherProfile (kept up-to-date by reviews)
        $averageRating = $user->teacherProfile?->rating ?? 0;

        // Upcoming sessions (as mentor — teacher may also run live sessions)
        $upcomingSessionsCount = MentorSession::where('mentor_id', $userId)
            ->where('status', 'upcoming')
            ->where('start_time', '>', now())
            ->count();

        // ── Earnings ─────────────────────────────────────────────
        $totalEarnings = Earning::where('user_id', $userId)->sum('amount');

        $thisMonth = Earning::where('user_id', $userId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $lastMonth = Earning::where('user_id', $userId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('amount');

        $monthlyGrowth = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : ($thisMonth > 0 ? 100 : 0);

        // Last 6 months chart data
        $earningsChart = collect(range(5, 0))->map(function ($monthsAgo) use ($userId) {
            $date  = now()->subMonths($monthsAgo);
            $total = Earning::where('user_id', $userId)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('amount');
            return [
                'month'  => $date->format('M'),
                'amount' => round($total, 2),
            ];
        })->values();

        // ── Upcoming Sessions ─────────────────────────────────────
        $upcomingSessions = MentorSession::where('mentor_id', $userId)
            ->where('status', 'upcoming')
            ->where('start_time', '>', now())
            ->with(['bookings.student.profile'])
            ->orderBy('start_time')
            ->take(5)
            ->get()
            ->map(function ($session) {
                $firstBooking = $session->bookings->first();
                return [
                    'id'            => $session->id,
                    'title'         => $session->title,
                    'type'          => $session->type,
                    'start_time'    => $session->start_time,
                    'end_time'      => $session->end_time,
                    'duration_mins' => $session->duration_minutes,
                    'seats_left'    => $session->seatsAvailable(),
                    'seats_booked'  => $session->seats_booked,
                    'max_seats'     => $session->max_seats,
                    'language'      => $session->language,
                    'price'         => $session->price,
                    'latest_student'=> $firstBooking ? [
                        'id'        => $firstBooking->student->id,
                        'name'      => $firstBooking->student->name,
                        'photo_url' => $firstBooking->student->photo_url,
                    ] : null,
                ];
            });

        // ── Recent Courses ────────────────────────────────────────
        $recentCourses = Course::where('teacher_id', $userId)
            ->with(['category', 'level'])
            ->withCount('enrollments')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'title'            => $c->title,
                'category'         => $c->category?->name,
                'level'            => $c->level?->name,
                'thumbnail'        => $c->thumbnail ? asset('storage/' . $c->thumbnail) : null,
                'status'           => $c->status,
                'is_published'     => $c->status === 'published',
                'rating'           => $c->rating,
                'enrollments_count'=> $c->enrollments_count,
                'created_at'       => $c->created_at,
            ]);

        // ── Response ──────────────────────────────────────────────
        return ApiResponse::success([
            'user' => [
                'id'        => $user->id,
                'name'      => $user->name,
                'full_name' => $user->full_name,
                'photo_url' => $user->photo_url,
                'role'      => $user->role,
                'can_switch_role' => is_null($user->pending_role),
            ],
            'stats' => [
                'total_courses_published' => $publishedCourses,
                'total_enrollments'       => $totalEnrollments,
                'average_rating'          => round($averageRating, 1),
                'upcoming_sessions_count' => $upcomingSessionsCount,
                'total_earnings'          => round($totalEarnings, 2),
                'this_month_earnings'     => round($thisMonth, 2),
                'last_month_earnings'     => round($lastMonth, 2),
                'monthly_growth_percent'  => $monthlyGrowth,
            ],
            'earnings_chart'    => $earningsChart,
            'upcoming_sessions' => $upcomingSessions,
            'recent_courses'    => $recentCourses,
        ]);
    }
}
