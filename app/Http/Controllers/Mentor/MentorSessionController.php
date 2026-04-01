<?php

namespace App\Http\Controllers\Mentor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mentor\StoreSessionRequest;
use App\Models\AppNotification;
use App\Models\MentorSession;
use App\Services\EarningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MentorSessionController extends Controller
{
    /**
     * GET /mentor/sessions
     *
     * Query params:
     *   ?period=weekly|monthly|yearly|all   — filter history by time range (default: monthly)
     *   ?page=N                             — paginate history (10 per page)
     */
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $period = $request->query('period', 'monthly');

        $totalSessions    = MentorSession::where('mentor_id', $user->id)->count();
        $upcomingSessions = MentorSession::where('mentor_id', $user->id)
            ->where('status', 'upcoming')
            ->count();

        $upcoming = MentorSession::where('mentor_id', $user->id)
            ->where('status', 'upcoming')
            ->with(['bookings.student.profile'])
            ->orderBy('start_time')
            ->get()
            ->map(fn($s) => $this->formatSession($s));

        $historyQuery = MentorSession::where('mentor_id', $user->id)
            ->whereIn('status', ['completed', 'cancelled'])
            ->with(['bookings.student.profile']);

        // Apply period filter to history
        match ($period) {
            'weekly'  => $historyQuery->where('end_time', '>=', now()->startOfWeek()),
            'monthly' => $historyQuery->where('end_time', '>=', now()->startOfMonth()),
            'yearly'  => $historyQuery->where('end_time', '>=', now()->startOfYear()),
            default   => null, // 'all' — no date constraint
        };

        $history = $historyQuery
            ->orderByDesc('end_time')
            ->paginate(10);

        // Shape history items to include earnings per session
        $history->getCollection()->transform(fn($s) => $this->formatSessionHistory($s));

        return ApiResponse::success([
            'stats' => [
                'total_sessions'    => $totalSessions,
                'upcoming_sessions' => $upcomingSessions,
            ],
            'period'   => $period,
            'upcoming' => $upcoming,
            'history'  => $history,
        ]);
    }

    /**
     * Sessions for a specific calendar date.
     * GET /mentor/sessions/calendar?date=2025-06-08
     */
    public function calendarSessions(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date = $request->date;

        $sessions = MentorSession::where('mentor_id', $request->user()->id)
            ->whereDate('start_time', $date)
            ->with(['bookings.student.profile'])
            ->orderBy('start_time')
            ->get()
            ->map(fn($s) => $this->formatSession($s));

        return ApiResponse::success($sessions);
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        if (!$request->user()->hasActiveSubscription()) {
            return ApiResponse::error(
                'You need an active subscription to create sessions.',
                402,
                ['subscription_required' => true]
            );
        }

        $isGroup  = $request->type === 'group';
        $maxSeats = $isGroup
            ? min($request->max_seats ?? MentorSession::GROUP_MAX_SEATS, MentorSession::GROUP_MAX_SEATS)
            : 1;

        $session = MentorSession::create([
            'mentor_id'        => $request->user()->id,
            'title'            => $request->title,
            'type'             => $request->type,
            'start_time'       => $request->start_time,
            'end_time'         => $request->end_time,
            'max_seats'        => $maxSeats,
            'group_max_seats'  => $isGroup ? $maxSeats : 1,
            'language'         => $request->language,
            'price'            => $request->price,
            'duration_minutes' => $request->duration_minutes,
            'join_key'         => $isGroup ? $this->generateJoinKey() : null,
            'meeting_room_id'  => 'torrino-' . Str::uuid(),
        ]);

        return ApiResponse::created($this->formatSession($session), 'Session created');
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)
            ->with(['bookings.student.profile', 'reviews.fromUser.profile'])
            ->find($id);

        if (!$session) {
            return ApiResponse::notFound('Session not found');
        }

        return ApiResponse::success($this->formatSession($session));
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
            'max_seats'        => 'sometimes|integer|min:1|max:' . MentorSession::GROUP_MAX_SEATS,
            'language'         => 'sometimes|string|max:50',
            'price'            => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:1',
        ]);

        $data = $request->only(['title', 'start_time', 'end_time', 'language', 'price', 'duration_minutes']);

        if ($request->has('max_seats') && $session->type === 'group') {
            $data['max_seats']       = min($request->max_seats, MentorSession::GROUP_MAX_SEATS);
            $data['group_max_seats'] = $data['max_seats'];
        }

        $session->update($data);

        return ApiResponse::success($this->formatSession($session->fresh()), 'Session updated.');
    }

    // ─── Heartbeat (keep-alive) ───────────────────────────────────

    /**
     * POST /mentor/sessions/{id}/heartbeat
     * Mentor app calls this every ~30s while in the video call.
     * Used to detect orphaned (abandoned) ongoing sessions.
     */
    public function heartbeat(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)
            ->where('status', 'ongoing')
            ->find($id);

        if (!$session) {
            return ApiResponse::notFound('Active session not found.');
        }

        $session->update(['mentor_last_ping_at' => now()]);

        return ApiResponse::success([
            'session_id' => $session->id,
            'status'     => $session->status,
            'pinged_at'  => $session->mentor_last_ping_at,
        ], 'Heartbeat recorded.');
    }

    // ─── Join Key ─────────────────────────────────────────────────

    /**
     * Get the join key for a group session (for sharing).
     * GET /mentor/sessions/{id}/join-key
     */
    public function getJoinKey(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)
            ->where('type', 'group')
            ->find($id);

        if (!$session) {
            return ApiResponse::notFound('Group session not found.');
        }

        if (!$session->join_key) {
            $session->update(['join_key' => $this->generateJoinKey()]);
        }

        return ApiResponse::success([
            'session_id'   => $session->id,
            'session_title'=> $session->title,
            'join_key'     => $session->join_key,
            'seats_left'   => $session->seatsAvailable(),
            'max_seats'    => $session->max_seats,
        ]);
    }

    /**
     * Regenerate join key for a group session.
     * POST /mentor/sessions/{id}/regenerate-key
     */
    public function regenerateJoinKey(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)
            ->where('type', 'group')
            ->where('status', 'upcoming')
            ->find($id);

        if (!$session) {
            return ApiResponse::notFound('Group session not found or already started.');
        }

        $newKey = $this->generateJoinKey();
        $session->update(['join_key' => $newKey]);

        return ApiResponse::success([
            'join_key' => $newKey,
        ], 'Join key regenerated. Share the new key — old key is now invalid.');
    }

    // ─── Session Lifecycle ────────────────────────────────────────

    /**
     * GET /mentor/sessions/{id}/meeting-info
     * Returns the Jitsi meeting room details for a session (mentor only).
     */
    public function getMeetingInfo(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)->find($id);

        if (!$session) {
            return ApiResponse::notFound('Session not found.');
        }

        // Auto-assign meeting_room_id if missing (for sessions created before this feature)
        if (!$session->meeting_room_id) {
            $session->update(['meeting_room_id' => 'torrino-' . Str::uuid()]);
        }

        return ApiResponse::success([
            'session_id'      => $session->id,
            'title'           => $session->title,
            'type'            => $session->type,
            'status'          => $session->status,
            'meeting_room_id' => $session->meeting_room_id,
            'jitsi_room'      => $session->meeting_room_id,
            'join_key'        => $session->type === 'group' ? $session->join_key : null,
            'start_time'      => $session->start_time,
            'end_time'        => $session->end_time,
        ]);
    }

    public function startSession(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)->find($id);

        if (!$session) {
            return ApiResponse::notFound('Session not found');
        }

        if (!in_array($session->status, ['upcoming', 'ongoing'])) {
            return ApiResponse::error(
                'Session cannot be started (current status: ' . $session->status . ').',
                422,
                ['session_status' => $session->status]
            );
        }

        // Auto-assign meeting_room_id if missing
        if (!$session->meeting_room_id) {
            $session->update(['meeting_room_id' => 'torrino-' . Str::uuid()]);
            $session->refresh();
        }

        if ($session->status !== 'ongoing') {
            $session->update(['status' => 'ongoing']);

            // Notify all booked students that the session has started
            foreach ($session->bookings()->where('status', 'confirmed')->get() as $booking) {
                AppNotification::create([
                    'user_id' => $booking->student_id,
                    'title'   => 'Session Started',
                    'body'    => 'Your session "' . $session->title . '" has started. Join now!',
                    'type'    => 'session_started',
                ]);
            }
        }

        return ApiResponse::success([
            'session'         => $this->formatSession($session->fresh()),
            'meeting_room_id' => $session->meeting_room_id,
            'jitsi_room'      => $session->meeting_room_id,
        ], 'Session started');
    }

    public function completeSession(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)->find($id);

        if (!$session) {
            return ApiResponse::notFound('Session not found');
        }

        if ($session->status !== 'ongoing') {
            return ApiResponse::error(
                'Only an ongoing session can be completed (current status: ' . $session->status . ').',
                422,
                ['session_status' => $session->status]
            );
        }

        $session->update(['status' => 'completed']);

        // Notify all booked students
        foreach ($session->bookings()->where('status', 'confirmed')->get() as $booking) {
            AppNotification::create([
                'user_id' => $booking->student_id,
                'title'   => 'Session Completed',
                'body'    => 'Your session "' . $session->title . '" has been completed. You can now leave a review.',
                'type'    => 'session_completed',
            ]);
        }

        // Create earning once per session (idempotent via firstOrCreate in EarningService)
        if ($session->price > 0) {
            app(EarningService::class)->createForSession($request->user()->id, $session);
        }

        return ApiResponse::success($session, 'Session completed');
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $session = MentorSession::where('mentor_id', $request->user()->id)
            ->with('bookings')
            ->find($id);

        if (!$session) {
            return ApiResponse::notFound('Session not found');
        }

        $session->update(['status' => 'cancelled']);

        // Cancel all associated bookings and notify students
        $confirmedBookings = $session->bookings()->whereIn('status', ['confirmed', 'pending'])->get();
        foreach ($confirmedBookings as $booking) {
            $booking->update(['status' => 'cancelled']);

            AppNotification::create([
                'user_id' => $booking->student_id,
                'title'   => 'Session Cancelled',
                'body'    => 'The session "' . $session->title . '" has been cancelled by the mentor.',
                'type'    => 'session_cancelled',
            ]);
        }

        return ApiResponse::success(null, 'Session cancelled');
    }

    // ─── Private Helpers ──────────────────────────────────────────

    private function generateJoinKey(): string
    {
        do {
            $key = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (MentorSession::where('join_key', $key)->exists());

        return $key;
    }

    private function formatSession(MentorSession $session): array
    {
        $bookings = $session->relationLoaded('bookings') ? $session->bookings : null;

        // For the sessions screen card: show primary student (first confirmed booking)
        $primaryStudent = null;
        if ($bookings && $bookings->isNotEmpty()) {
            $first          = $bookings->first();
            $student        = $first->student ?? null;
            $studentProfile = $student?->profile ?? null;
            $primaryStudent = $student ? [
                'id'    => $student->id,
                'name'  => $student->full_name ?? $student->name,
                'photo' => $student->photo_url,
                'role'  => 'Student',
            ] : null;
        }

        return [
            'id'              => $session->id,
            'title'           => $session->title,
            'type'            => $session->type,
            'start_time'      => $session->start_time,
            'end_time'        => $session->end_time,
            'duration_mins'   => $session->duration_minutes,
            'language'        => $session->language,
            'price'           => $session->price,
            'status'          => $session->status,
            'max_seats'       => $session->max_seats,
            'seats_booked'    => $session->seats_booked,
            'seats_left'      => $session->seatsAvailable(),
            'is_group'        => $session->type === 'group',
            'join_key'        => $session->type === 'group' ? $session->join_key : null,
            'meeting_room_id' => $session->meeting_room_id,
            'primary_student' => $primaryStudent,
            'bookings'        => $bookings,
        ];
    }

    /**
     * Same as formatSession but includes earnings amount for the history list card (+$30).
     */
    private function formatSessionHistory(MentorSession $session): array
    {
        $base = $this->formatSession($session);

        // Confirmed bookings count × price = total earned for this session
        $confirmedCount      = $session->relationLoaded('bookings')
            ? $session->bookings->where('status', 'confirmed')->count()
            : 0;

        $base['earned']      = round($session->price * max($confirmedCount, 1), 2);
        $base['earned_label'] = '+$' . number_format($base['earned'], 0);

        return $base;
    }
}
