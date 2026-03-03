<?php

namespace App\Http\Controllers\Student;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\BookSessionRequest;
use App\Models\MentorSession;
use App\Models\SessionBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bookings = $request->user()
            ->sessionBookings()
            ->with(['session.mentor.profile', 'session.mentor.mentorProfile'])
            ->orderByDesc('created_at')
            ->paginate(10);

        return ApiResponse::success($bookings);
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

        return ApiResponse::created($booking->load('session'), 'Session booked successfully');
    }

    public function cancel(int $id, Request $request): JsonResponse
    {
        $booking = SessionBooking::where('student_id', $request->user()->id)->find($id);

        if (!$booking) {
            return ApiResponse::notFound('Booking not found');
        }

        $booking->update(['status' => 'cancelled']);
        $booking->session->decrement('seats_booked');

        return ApiResponse::success(null, 'Booking cancelled');
    }
}
