<?php

namespace App\Http\Controllers\Student;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentHomeController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $coursesInProgress  = $user->enrolledCourses()->where('status', 'active')->count();
        $sessionsBooked     = $user->sessionBookings()->whereIn('status', ['confirmed', 'pending'])->count();
        $certificatesEarned = $user->studentProfile?->certificates_earned ?? 0;

        $upcomingSessions = $user->sessionBookings()
            ->with(['session.mentor.profile', 'session.mentor.mentorProfile'])
            ->whereHas('session', fn($q) => $q->where('status', 'upcoming'))
            ->take(5)
            ->get();

        $recommendedMentors = User::with(['profile', 'mentorProfile'])
            ->whereHas('roles', fn($q) => $q->where('name', 'mentor'))
            ->whereHas('mentorProfile', fn($q) => $q->orderByDesc('rating'))
            ->take(5)
            ->get();

        $recommendedTeachers = User::with(['profile', 'teacherProfile'])
            ->whereHas('roles', fn($q) => $q->where('name', 'teacher'))
            ->take(5)
            ->get();

        $popularCourses = Course::with('teacher.profile')
            ->where('status', 'published')
            ->orderByDesc('total_enrollments')
            ->take(5)
            ->get();

        return ApiResponse::success([
            'stats' => [
                'courses_in_progress'  => $coursesInProgress,
                'sessions_booked'      => $sessionsBooked,
                'certificates_earned'  => $certificatesEarned,
            ],
            'upcoming_sessions'   => $upcomingSessions,
            'recommended_mentors' => $recommendedMentors,
            'recommended_teachers'=> $recommendedTeachers,
            'popular_courses'     => $popularCourses,
        ]);
    }
}
