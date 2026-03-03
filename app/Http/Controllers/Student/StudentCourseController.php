<?php

namespace App\Http\Controllers\Student;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentCourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courses = Course::with('teacher.profile')
            ->where('status', 'published')
            ->when($request->search, fn($q) => $q->where('title', 'like', '%' . $request->search . '%'))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->language, fn($q) => $q->where('language', $request->language))
            ->orderByDesc('total_enrollments')
            ->paginate(10);

        return ApiResponse::success($courses);
    }

    public function show(int $id): JsonResponse
    {
        $course = Course::with(['teacher.profile', 'lessons', 'reviews.fromUser.profile'])->find($id);

        if (!$course) {
            return ApiResponse::notFound('Course not found');
        }

        return ApiResponse::success($course);
    }

    public function enroll(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $course  = Course::find($request->course_id);
        $student = $request->user();

        if ($course->isEnrolledBy($student->id)) {
            return ApiResponse::error('Already enrolled in this course');
        }

        $enrollment = CourseEnrollment::create([
            'course_id'  => $course->id,
            'student_id' => $student->id,
            'amount_paid'=> $course->price,
        ]);

        $course->increment('total_enrollments');

        if ($course->teacher_id) {
            \App\Models\Earning::create([
                'user_id'        => $course->teacher_id,
                'amount'         => $course->price - $course->platform_fee,
                'type'           => 'course',
                'description'    => 'Enrollment: ' . $course->title,
                'reference_id'   => $course->id,
                'reference_type' => 'Course',
            ]);
        }

        return ApiResponse::created($enrollment->load('course'), 'Enrolled successfully');
    }

    public function myCourses(Request $request): JsonResponse
    {
        $enrollments = $request->user()
            ->enrolledCourses()
            ->with(['course.teacher.profile', 'course.lessons'])
            ->where('status', 'active')
            ->get();

        return ApiResponse::success($enrollments);
    }

    public function completedCourses(Request $request): JsonResponse
    {
        $enrollments = $request->user()
            ->enrolledCourses()
            ->with(['course.teacher.profile'])
            ->where('status', 'completed')
            ->get();

        return ApiResponse::success($enrollments);
    }

    public function completeLesson(int $courseId, int $lessonId, Request $request): JsonResponse
    {
        $enrollment = $request->user()
            ->enrolledCourses()
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return ApiResponse::error('You are not enrolled in this course.', 403);
        }

        $lesson = \App\Models\Lesson::where('course_id', $courseId)->find($lessonId);

        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found.');
        }

        // Track completed lesson IDs in the enrollment's progress JSON column
        $progress = $enrollment->progress ?? [];
        if (!in_array($lessonId, $progress)) {
            $progress[] = $lessonId;
            $enrollment->update(['progress' => $progress]);
        }

        // Check if all lessons are done
        $totalLessons     = \App\Models\Lesson::where('course_id', $courseId)->count();
        $completedLessons = count($progress);
        $isComplete       = $completedLessons >= $totalLessons;

        if ($isComplete && $enrollment->status !== 'completed') {
            $enrollment->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            // Increment student's completed courses counter
            $request->user()->studentProfile?->increment('courses_completed');
        }

        return ApiResponse::success([
            'completed_lessons' => $completedLessons,
            'total_lessons'     => $totalLessons,
            'progress_percent'  => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0,
            'course_completed'  => $isComplete,
        ], 'Lesson marked as completed.');
    }

    public function courseProgress(int $courseId, Request $request): JsonResponse
    {
        $enrollment = $request->user()
            ->enrolledCourses()
            ->where('course_id', $courseId)
            ->first();

        if (!$enrollment) {
            return ApiResponse::error('You are not enrolled in this course.', 403);
        }

        $lessons      = \App\Models\Lesson::where('course_id', $courseId)->orderBy('order')->get();
        $progress     = $enrollment->progress ?? [];
        $totalLessons = $lessons->count();

        return ApiResponse::success([
            'enrollment_status' => $enrollment->status,
            'completed_lessons' => count($progress),
            'total_lessons'     => $totalLessons,
            'progress_percent'  => $totalLessons > 0 ? round((count($progress) / $totalLessons) * 100) : 0,
            'lessons'           => $lessons->map(fn($l) => [
                'id'          => $l->id,
                'title'       => $l->title,
                'order'       => $l->order,
                'duration'    => $l->duration,
                'is_free'     => $l->is_free,
                'is_complete' => in_array($l->id, $progress),
            ]),
        ]);
    }
}
