<?php

namespace App\Http\Controllers\Mentor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mentor\StoreSessionRequest;
use App\Models\MentorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalSessions    = MentorSession::where('mentor_id', $user->id)->count();
        $upcomingSessions = MentorSession::where('mentor_id', $user->id)->where('status', 'upcoming')->count();

        $upcoming = MentorSession::where('mentor_id', $user->id)
            ->where('status', 'upcoming')
            ->with(['bookings.student.profile'])
            ->orderBy('start_time')
            ->get();

        $history = MentorSession::where('mentor_id', $user->id)
            ->whereIn('status', ['completed', 'cancelled'])
            ->with(['bookings.student.profile'])
            ->orderByDesc('end_time')
            ->paginate(10);

        return ApiResponse::success([
            'stats' => [
                'total_sessions'    => $totalSessions,
                'upcoming_sessions' => $upcomingSessions,
            ],
            'upcoming' => $upcoming,
            'history'  => $history,
        ]);
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        // Mentor must have an active subscription to create sessions
        if (!$request->user()->hasActiveSubscription()) {
            return ApiResponse::error(
                'You need an active subscription to create sessions.',
                402,
                ['subscription_required' => true]
            );
        }

        $session = MentorSession::create([
            'mentor_id'        => $request->user()->id,
            'title'            => $request->title,
            'type'             => $request->type,
            'start_time'       => $request->start_time,
            'end_time'         => $request->end_time,
            'max_seats'        => $request->max_seats,
            'language'         => $request->language,
            'price'            => $request->price,
            'duration_minutes' => $request->duration_minutes,
        ]);

        return ApiResponse::created($session, 'Session created');
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)
            ->where('status', 'upcoming')
            ->find($id);

        if (!$session) {
            return ApiResponse::notFound('Session not found or cannot be edited.');
        }

        $request->validate([
            'title'            => 'sometimes|string|max:255',
            'start_time'       => 'sometimes|date',
            'end_time'         => 'sometimes|date|after:start_time',
            'max_seats'        => 'sometimes|integer|min:1',
            'language'         => 'sometimes|string|max:50',
            'price'            => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:1',
        ]);

        $session->update($request->only([
            'title', 'start_time', 'end_time',
            'max_seats', 'language', 'price', 'duration_minutes',
        ]));

        return ApiResponse::success($session, 'Session updated.');
    }

    public function show(int $id): JsonResponse
    {
        $session = MentorSession::with(['bookings.student.profile', 'reviews.fromUser.profile'])->find($id);

        if (!$session) {
            return ApiResponse::notFound('Session not found');
        }

        return ApiResponse::success($session);
    }

    public function startSession(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)->find($id);

        if (!$session) {
            return ApiResponse::notFound('Session not found');
        }

        $session->update(['status' => 'ongoing']);

        return ApiResponse::success($session, 'Session started');
    }

    public function completeSession(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)->find($id);

        if (!$session) {
            return ApiResponse::notFound('Session not found');
        }

        $session->update(['status' => 'completed']);

        // credit earnings per booking
        foreach ($session->bookings()->where('status', 'confirmed')->get() as $booking) {
            $request->user()->earnings()->create([
                'amount'         => $session->price,
                'type'           => 'session',
                'description'    => 'Session: ' . $session->title,
                'reference_id'   => $session->id,
                'reference_type' => 'MentorSession',
            ]);
        }

        return ApiResponse::success($session, 'Session completed');
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)->find($id);

        if (!$session) {
            return ApiResponse::notFound('Session not found');
        }

        $session->update(['status' => 'cancelled']);

        return ApiResponse::success(null, 'Session cancelled');
    }
}
