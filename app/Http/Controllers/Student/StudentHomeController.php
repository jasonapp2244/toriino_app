<?php

namespace App\Http\Controllers\Student;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentHomeController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'profile',
            'studentProfile',
            'activeSubscription',
        ]);

        // ── 1. Stats ─────────────────────────────────────────────
        $coursesInProgress  = $user->enrolledCourses()->where('status', 'active')->count();
        $sessionsBooked     = $user->sessionBookings()->whereIn('status', ['confirmed', 'pending'])->count();
        $certificatesEarned = $user->studentProfile?->certificates_earned ?? 0;

        // ── 2. AI Tutor Banner ────────────────────────────────────
        $hasPremium = $user->hasActiveSubscription();

        // ── 3. Ongoing Course ─────────────────────────────────────
        // Most recently updated active enrollment
        $ongoingEnrollment = $user->enrolledCourses()
            ->with(['course.teacher.profile', 'course.teacher.teacherProfile'])
            ->where('status', 'active')
            ->orderByDesc('updated_at')
            ->first();

        $ongoingCourse = null;
        if ($ongoingEnrollment && $ongoingEnrollment->course) {
            $c       = $ongoingEnrollment->course;
            $teacher = $c->teacher;

            $ongoingCourse = [
                'enrollment_id'    => $ongoingEnrollment->id,
                'course_id'        => $c->id,
                'title'            => $c->title,
                'thumbnail'        => $c->thumbnail
                    ? asset('storage/' . $c->thumbnail)
                    : null,
                'rating'           => $c->rating,
                'progress_percent' => $ongoingEnrollment->progress_percent,
                'last_seen_at'     => $ongoingEnrollment->updated_at,
                'teacher' => $teacher ? [
                    'id'        => $teacher->id,
                    'name'      => $teacher->name,
                    'photo_url' => $teacher->photo_url,
                    'rating'    => $teacher->teacherProfile?->rating,
                ] : null,
            ];
        }

        // ── 4. Recommended Mentors ────────────────────────────────
        $recommendedMentors = User::with(['profile', 'mentorProfile'])
            ->whereHas('roles', fn($q) => $q->where('name', 'mentor'))
            ->whereHas('mentorProfile')
            ->orderByDesc(
                \App\Models\MentorProfile::select('rating')
                    ->whereColumn('user_id', 'users.id')
                    ->limit(1)
            )
            ->take(5)
            ->get()
            ->map(fn($m) => [
                'id'           => $m->id,
                'name'         => $m->name,
                'photo_url'    => $m->photo_url,
                'rating'       => $m->mentorProfile?->rating,
                'total_reviews'=> $m->mentorProfile?->total_reviews,
                'designation'  => $m->mentorProfile?->designation,
                'short_bio'    => $m->mentorProfile?->short_bio,
                'expertise'    => $m->mentorProfile?->expertise_list ?? [],
                'languages'    => $m->mentorProfile?->languages_list ?? [],
                'price_per_hour' => $m->mentorProfile?->price_per_hour,
                'experience_years' => $m->mentorProfile?->experience_years,
                'is_verified'  => $m->mentorProfile?->is_verified,
            ]);

        // ── 5. Recommended Teachers ───────────────────────────────
        $recommendedTeachers = User::with(['profile', 'teacherProfile'])
            ->withCount([
                'courses as available_courses_count' => fn($q) => $q->where('status', 'published'),
            ])
            ->whereHas('roles', fn($q) => $q->where('name', 'teacher'))
            ->whereHas('teacherProfile')
            ->orderByDesc(
                \App\Models\TeacherProfile::select('rating')
                    ->whereColumn('user_id', 'users.id')
                    ->limit(1)
            )
            ->take(5)
            ->get()
            ->map(fn($t) => [
                'id'               => $t->id,
                'name'             => $t->name,
                'photo_url'        => $t->photo_url,
                'rating'           => $t->teacherProfile?->rating,
                'total_reviews'    => $t->teacherProfile?->total_reviews,
                'designation'      => $t->teacherProfile?->designation,
                'short_bio'        => $t->teacherProfile?->short_bio,
                'subject'          => $t->teacherProfile?->subject,
                'expertise'        => $t->teacherProfile?->expertise_list ?? [],
                'languages'        => $t->teacherProfile?->languages_list ?? [],
                'experience_years' => $t->teacherProfile?->experience_years,
                'is_verified'      => $t->teacherProfile?->is_verified,
                'available_courses'=> $t->available_courses_count ?? 0,
            ]);

        // ── 6. Upcoming Sessions ──────────────────────────────────
        $upcomingSessions = $user->sessionBookings()
            ->with([
                'session.mentor.profile',
                'session.mentor.mentorProfile',
            ])
            ->whereHas('session', fn($q) => $q
                ->where('status', 'upcoming')
                ->where('start_time', '>=', now())
            )
            ->orderBy(
                \App\Models\MentorSession::select('start_time')
                    ->whereColumn('id', 'session_bookings.session_id')
                    ->limit(1)
            )
            ->take(5)
            ->get()
            ->map(fn($b) => [
                'booking_id'     => $b->id,
                'booking_status' => $b->status,
                'session' => $b->session ? [
                    'id'               => $b->session->id,
                    'title'            => $b->session->title,
                    'type'             => $b->session->type,
                    'start_time'       => $b->session->start_time,
                    'end_time'         => $b->session->end_time,
                    'duration_minutes' => $b->session->duration_minutes,
                    'price'            => $b->session->price,
                    'mentor' => $b->session->mentor ? [
                        'id'        => $b->session->mentor->id,
                        'name'      => $b->session->mentor->name,
                        'photo_url' => $b->session->mentor->photo_url,
                        'rating'    => $b->session->mentor->mentorProfile?->rating,
                    ] : null,
                ] : null,
            ]);

        // ── 7. Popular Courses ────────────────────────────────────
        $popularCourses = Course::with(['teacher.profile', 'category', 'level'])
            ->where('status', 'published')
            ->orderByDesc('total_enrollments')
            ->take(5)
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'title'        => $c->title,
                'thumbnail'    => $c->thumbnail
                    ? asset('storage/' . $c->thumbnail)
                    : null,
                'price'        => $c->price,
                'is_free'      => ($c->price ?? 0) == 0,
                'rating'       => $c->rating,
                'duration'     => $c->duration,
                'category'     => $c->category?->name,
                'level'        => $c->level?->name,
                'enrollments'  => $c->total_enrollments,
                'teacher' => $c->teacher ? [
                    'id'        => $c->teacher->id,
                    'name'      => $c->teacher->name,
                    'photo_url' => $c->teacher->photo_url,
                ] : null,
            ]);

        return ApiResponse::success([
            'student' => [
                'id'        => $user->id,
                'name'      => $user->name,
                'photo_url' => $user->photo_url,
            ],
            'stats' => [
                'courses_in_progress'  => $coursesInProgress,
                'sessions_booked'      => $sessionsBooked,
                'certificates_earned'  => $certificatesEarned,
            ],
            'ai_tutor' => [
                'has_premium' => $hasPremium,
            ],
            'ongoing_course'       => $ongoingCourse,
            'recommended_mentors'  => $recommendedMentors,
            'recommended_teachers' => $recommendedTeachers,
            'upcoming_sessions'    => $upcomingSessions,
            'popular_courses'      => $popularCourses,
        ]);
    }
}
