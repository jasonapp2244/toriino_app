<?php

namespace App\Http\Controllers\Mentor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Earning;
use App\Models\MentorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorHomeController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalSessions   = MentorSession::where('mentor_id', $user->id)->count();
        $upcomingSessions = MentorSession::where('mentor_id', $user->id)
            ->where('status', 'upcoming')
            ->count();
        $avgRating       = $user->reviewsReceived()->avg('rating') ?? 0;
        $totalEarnings   = Earning::where('user_id', $user->id)->sum('amount');

        $upcoming = MentorSession::where('mentor_id', $user->id)
            ->where('status', 'upcoming')
            ->with(['bookings.student.profile'])
            ->orderBy('start_time')
            ->take(3)
            ->get();

        $recentHistory = MentorSession::where('mentor_id', $user->id)
            ->where('status', 'completed')
            ->with(['bookings.student.profile'])
            ->orderByDesc('end_time')
            ->take(10)
            ->get();

        return ApiResponse::success([
            'stats' => [
                'total_sessions'    => $totalSessions,
                'upcoming_sessions' => $upcomingSessions,
                'average_rating'    => round($avgRating, 1),
                'total_earnings'    => round($totalEarnings, 2),
            ],
            'upcoming_sessions' => $upcoming,
            'recent_history'    => $recentHistory,
        ]);
    }
}
