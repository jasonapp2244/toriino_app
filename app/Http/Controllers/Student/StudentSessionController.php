<?php

namespace App\Http\Controllers\Student;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\BookSessionRequest;
use App\Models\AppNotification;
use App\Models\MentorSession;
use App\Models\Review;
use App\Models\SessionBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentSessionController extends Controller
{
    /**
     * GET /student/sessions
     *
     * Query params:
     *   ?status=upcoming|completed|cancelled|all  — filter by booking status (default: all)
     *   ?period=weekly|monthly|yearly             — filter completed history by time range
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'all');
        $period = $request->query('period', null);

        $query = $request->user()
            ->sessionBookings()
            ->with(['session.mentor.profile', 'session.mentor.mentorProfile']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Period filter on the underlying session's end_time
        if ($period) {
            $query->whereHas('session', function ($q) use ($period) {
                match ($period) {
                    'weekly'  => $q->where('end_time', '>=', now()->startOfWeek()),
                    'monthly' => $q->where('end_time', '>=', now()->startOfMonth()),
                    'yearly'  => $q->where('end_time', '>=', now()->startOfYear()),
                    default   => null,
                };
            });
        }

        $bookings = $query->orderByDesc('created_at')->paginate(10);

        return ApiResponse::success($bookings);
    }

    /**
     * GET /student/sessions/{id}
     * Single booking detail for the student, including session and mentor info.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $booking = SessionBooking::where('student_id', $request->user()->id)
            ->with([
                'session.mentor.profile',
                'session.mentor.mentorProfile',
                'session.reviews' => fn($q) => $q->where('from_user_id', $request->user()->id),
            ])
            ->find($id);

        if (!$booking) {
            return ApiResponse::notFound('Booking not found.');
        }

        $session    = $booking->session;
        $mentor     = $session->mentor;
        $myReview   = $session->reviews->first();

        return ApiResponse::success([
            'booking' => [
                'id'           => $booking->id,
                'status'       => $booking->status,
                'booked_at'    => $booking->created_at->toIso8601String(),
            ],
            'session' => [
                'id'            => $session->id,
                'title'         => $session->title,
                'type'          => $session->type,
                'start_time'    => $session->start_time,
                'end_time'      => $session->end_time,
                'duration_mins' => $session->duration_minutes,
                'language'      => $session->language,
                'price'         => $session->price,
                'status'        => $session->status,
                'seats_left'    => $session->seatsAvailable(),
                'max_seats'     => $session->max_seats,
                'join_key'      => $session->type === 'group' ? $session->join_key : null,
            ],
            'mentor' => [
                'id'          => $mentor->id,
                'name'        => $mentor->full_name ?? $mentor->name,
                'photo'       => $mentor->photo_url,
                'designation' => $mentor->mentorProfile?->designation,
                'rating'      => round($mentor->reviewsReceived()->avg('rating') ?? 0, 1),
            ],
            'my_review' => $myReview ? [
                'id'      => $myReview->id,
                'rating'  => $myReview->rating,
                'comment' => $myReview->comment,
            ] : null,
            'can_review' => $session->status === 'completed' && is_null($myReview),
        ]);
    }

    public function book(BookSessionRequest $request): JsonResponse
    {

        $session = MentorSession::find($request->session_id);

        if ($session->status !== 'upcoming') {
            return ApiResponse::error('Session is not available for booking');
        }

        if ($session->seatsAvailable() <= 0) {
            return ApiResponse::error('No seats available');
        }

        $alreadyBooked = SessionBooking::where('session_id', $session->id)
            ->where('student_id', $request->user()->id)
            ->exists();

        if ($alreadyBooked) {
            return ApiResponse::error('You have already booked this session');
        }

        $booking = SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $request->user()->id,
            'status'     => 'confirmed',
        ]);

        $session->increment('seats_booked');

        // Notify mentor about the new booking
        AppNotification::create([
            'user_id' => $session->mentor_id,
            'title'   => 'New Session Booking',
            'body'    => $request->user()->name . ' booked your session "' . $session->title . '".',
            'type'    => 'session_booked',
        ]);

        return ApiResponse::created($booking->load('session'), 'Session booked successfully');
    }

    /**
     * POST /student/sessions/{id}/review
     * Student submits a review for a completed session (convenience endpoint).
     * The session is identified by the booking id.
     */
    public function submitReview(int $id, Request $request): JsonResponse
    {
        $booking = SessionBooking::where('student_id', $request->user()->id)->find($id);

        if (!$booking) {
            return ApiResponse::notFound('Booking not found.');
        }

        $session = $booking->session;

        if ($session->status !== 'completed') {
            return ApiResponse::error('You can only review a completed session.', 422);
        }

        $alreadyReviewed = Review::where('session_id', $session->id)
            ->where('from_user_id', $request->user()->id)
            ->exists();

        if ($alreadyReviewed) {
            return ApiResponse::error('You have already reviewed this session.', 422);
        }

        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = Review::create([
            'session_id'   => $session->id,
            'from_user_id' => $request->user()->id,
            'to_user_id'   => $session->mentor_id,
            'rating'       => $request->rating,
            'comment'      => $request->comment,
        ]);

        return ApiResponse::created($review, 'Review submitted successfully.');
    }

    /**
     * GET /student/sessions/{id}/status
     * Student polls this to check if session is still ongoing (e.g. after reconnect).
     * id = booking_id
     */
    public function sessionStatus(int $id, Request $request): JsonResponse
    {
        $booking = SessionBooking::where('student_id', $request->user()->id)->find($id);

        if (!$booking) {
            return ApiResponse::notFound('Booking not found.');
        }

        $session = $booking->session;

        return ApiResponse::success([
            'booking_status' => $booking->status,
            'session_status' => $session->status,
            'session_id'     => $session->id,
            'can_join'       => $session->status === 'ongoing' && $booking->status === 'confirmed',
            'meeting_room_id'=> $session->status === 'ongoing' ? $session->meeting_room_id : null,
        ]);
    }

    /**
     * GET /student/sessions/{id}/meeting-info
     * Returns Jitsi meeting info for a booked session.
     * The session must be 'ongoing' and the student must have a confirmed booking.
     */
    public function getMeetingInfo(int $id, Request $request): JsonResponse
    {
        $booking = SessionBooking::where('student_id', $request->user()->id)
            ->where('status', 'confirmed')
            ->find($id);

        if (!$booking) {
            return ApiResponse::notFound('Booking not found or not confirmed.');
        }

        $session = $booking->session;

        if (!$session) {
            return ApiResponse::notFound('Session not found.');
        }

        if ($session->status !== 'ongoing') {
            return ApiResponse::error(
                'Session has not started yet. Please wait for the mentor to start the session.',
                422,
                ['session_status' => $session->status]
            );
        }

        return ApiResponse::success([
            'booking_id'      => $booking->id,
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

    public function cancel(int $id, Request $request): JsonResponse
    {
        $booking = SessionBooking::where('student_id', $request->user()->id)->find($id);

        if (!$booking) {
            return ApiResponse::notFound('Booking not found');
        }

        if (!in_array($booking->status, ['confirmed', 'pending'])) {
            return ApiResponse::error('This booking cannot be cancelled.', 422);
        }

        $booking->update(['status' => 'cancelled']);
        $booking->session->decrement('seats_booked');

        return ApiResponse::success(null, 'Booking cancelled');
    }

    /**
     * Join a GROUP session using a join key shared by the mentor.
     * POST /student/sessions/join-by-key
     * Body: { "join_key": "ABCD-EFGH" }
     */
    public function joinByKey(Request $request): JsonResponse
    {
        $request->validate([
            'join_key' => 'required|string',
        ]);

        $session = MentorSession::where('join_key', strtoupper($request->join_key))
            ->where('type', 'group')
            ->first();

        if (!$session) {
            return ApiResponse::notFound('Invalid join key. Please check the code and try again.');
        }

        if ($session->status !== 'upcoming') {
            return ApiResponse::error('This session is no longer available for joining.');
        }

        if ($session->seatsAvailable() <= 0) {
            return ApiResponse::error(
                'This group session is full. Maximum ' . $session->max_seats . ' students allowed.',
                422,
                ['seats_left' => 0, 'max_seats' => $session->max_seats]
            );
        }

        $alreadyBooked = SessionBooking::where('session_id', $session->id)
            ->where('student_id', $request->user()->id)
            ->exists();

        if ($alreadyBooked) {
            return ApiResponse::error('You have already joined this session.');
        }

        $booking = SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $request->user()->id,
            'status'     => 'confirmed',
        ]);

        $session->increment('seats_booked');

        return ApiResponse::created([
            'booking'      => $booking,
            'session'      => [
                'id'           => $session->id,
                'title'        => $session->title,
                'type'         => $session->type,
                'start_time'   => $session->start_time,
                'end_time'     => $session->end_time,
                'language'     => $session->language,
                'price'        => $session->price,
                'seats_left'   => $session->seatsAvailable() - 1,
                'max_seats'    => $session->max_seats,
            ],
        ], 'Successfully joined the group session! Payment will be processed at session time.');
    }

    /**
     * POST /student/sessions/{id}/reschedule
     * Cancel the current booking and book a different available session with the same mentor.
     * Body: { "new_session_id": 123 }
     */
    public function reschedule(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'new_session_id' => 'required|integer|exists:mentor_sessions,id',
        ]);

        $booking = SessionBooking::where('student_id', $request->user()->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->find($id);

        if (!$booking) {
            return ApiResponse::notFound('Active booking not found.');
        }

        $oldSession = $booking->session;

        if ($oldSession->status !== 'upcoming') {
            return ApiResponse::error('Only upcoming sessions can be rescheduled.', 422);
        }

        if ($request->new_session_id == $oldSession->id) {
            return ApiResponse::error('New session must be different from the current session.', 422);
        }

        $newSession = MentorSession::find($request->new_session_id);

        if ($newSession->mentor_id !== $oldSession->mentor_id) {
            return ApiResponse::error('You can only reschedule to a session by the same mentor.', 422);
        }

        if ($newSession->status !== 'upcoming') {
            return ApiResponse::error('The selected session is not available for booking.', 422);
        }

        if ($newSession->seatsAvailable() <= 0) {
            return ApiResponse::error('No seats available in the selected session.', 422);
        }

        $alreadyBooked = SessionBooking::where('session_id', $newSession->id)
            ->where('student_id', $request->user()->id)
            ->exists();

        if ($alreadyBooked) {
            return ApiResponse::error('You have already booked the selected session.', 422);
        }

        // Cancel old booking
        $booking->update(['status' => 'cancelled']);
        $oldSession->decrement('seats_booked');

        // Book new session
        $newBooking = SessionBooking::create([
            'session_id' => $newSession->id,
            'student_id' => $request->user()->id,
            'status'     => 'confirmed',
        ]);
        $newSession->increment('seats_booked');

        return ApiResponse::success([
            'old_booking_id' => $booking->id,
            'new_booking'    => $newBooking->load('session.mentor.profile'),
            'new_session' => [
                'id'               => $newSession->id,
                'title'            => $newSession->title,
                'type'             => $newSession->type,
                'start_time'       => $newSession->start_time,
                'end_time'         => $newSession->end_time,
                'duration_minutes' => $newSession->duration_minutes,
                'language'         => $newSession->language,
                'price'            => $newSession->price,
                'seats_left'       => $newSession->seatsAvailable() - 1,
            ],
        ], 'Session rescheduled successfully.');
    }

    /**
     * GET /student/sessions/available/{mentorId}
     * List upcoming available sessions by a specific mentor (used when selecting a new session to reschedule to).
     */
    public function availableByMentor(int $mentorId, Request $request): JsonResponse
    {
        $sessions = MentorSession::with(['mentor.profile', 'mentor.mentorProfile'])
            ->where('mentor_id', $mentorId)
            ->where('status', 'upcoming')
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->get()
            ->map(fn($s) => [
                'id'               => $s->id,
                'title'            => $s->title,
                'type'             => $s->type,
                'start_time'       => $s->start_time,
                'end_time'         => $s->end_time,
                'duration_minutes' => $s->duration_minutes,
                'language'         => $s->language,
                'price'            => $s->price,
                'seats_left'       => $s->seatsAvailable(),
                'max_seats'        => $s->max_seats,
                'join_key'         => $s->type === 'group' ? $s->join_key : null,
                'mentor' => $s->mentor ? [
                    'id'          => $s->mentor->id,
                    'name'        => $s->mentor->name,
                    'photo_url'   => $s->mentor->photo_url,
                    'designation' => $s->mentor->mentorProfile?->designation,
                    'rating'      => $s->mentor->mentorProfile?->rating,
                ] : null,
            ]);

        return ApiResponse::success($sessions);
    }
}
