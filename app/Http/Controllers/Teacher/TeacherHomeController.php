<?php

namespace App\Http\Controllers\Teacher;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Earning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherHomeController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalCourses      = Course::where('teacher_id', $user->id)->count();
        $publishedCourses  = Course::where('teacher_id', $user->id)->where('status', 'published')->count();
        $totalStudents     = \App\Models\CourseEnrollment::whereHas('course', fn($q) => $q->where('teacher_id', $user->id))->count();
        $totalEarnings     = Earning::where('user_id', $user->id)->sum('amount');

        $popularCourses = Course::where('teacher_id', $user->id)
            ->with(['enrollments'])
            ->orderByDesc('total_enrollments')
            ->take(5)
            ->get();

        $recentEnrollments = \App\Models\CourseEnrollment::whereHas('course', fn($q) => $q->where('teacher_id', $user->id))
            ->with(['course', 'student.profile'])
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        return ApiResponse::success([
            'stats' => [
                'total_courses'     => $totalCourses,
                'published_courses' => $publishedCourses,
                'total_students'    => $totalStudents,
                'total_earnings'    => round($totalEarnings, 2),
            ],
            'popular_courses'     => $popularCourses,
            'recent_enrollments'  => $recentEnrollments,
        ]);
    }
}
