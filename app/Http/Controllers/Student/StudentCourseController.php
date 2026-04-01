<?php

namespace App\Http\Controllers\Student;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseCertificate;
use App\Models\Lesson;
use App\Models\LessonVideoProgress;
use App\Models\CourseFavorite;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class StudentCourseController extends Controller
{
    // Minimum percentage of video that must be watched to unlock next lesson
    private const COMPLETION_THRESHOLD = 90;

    public function index(Request $request): JsonResponse
    {
        $courses = Course::with(['teacher.profile', 'category', 'level'])
            ->where('status', 'published')
            ->when($request->search,      fn($q) => $q->where('title', 'like', '%' . $request->search . '%'))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->level_id,    fn($q) => $q->where('level_id', $request->level_id))
            ->when($request->language,    fn($q) => $q->where('language', $request->language))
            ->when($request->min_price,  fn($q) => $q->where('price', '>=', $request->min_price))
            ->when($request->max_price,  fn($q) => $q->where('price', '<=', $request->max_price))
            ->when($request->min_rating, fn($q) => $q->where('rating', '>=', $request->min_rating))
            ->orderByDesc('total_enrollments')
            ->paginate(10);

        return ApiResponse::success($courses);
    }

    public function show(int $id): JsonResponse
    {
        $course = Course::with(['teacher.profile', 'category', 'level', 'lessons.attachments', 'reviews.fromUser.profile'])
            ->find($id);

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

        if ($course->price > 0) {
            return ApiResponse::error('This is a paid course. Please complete payment to enroll.', 402);
        }

        $enrollment = CourseEnrollment::create([
            'course_id'  => $course->id,
            'student_id' => $student->id,
            'amount_paid'=> 0,
        ]);

        $course->increment('total_enrollments');

        // Notify teacher about the new enrollment
        if ($course->teacher_id) {
            AppNotification::create([
                'user_id' => $course->teacher_id,
                'title'   => 'New Course Enrollment',
                'body'    => $student->name . ' enrolled in your course "' . $course->title . '".',
                'type'    => 'course_enrolled',
            ]);
        }

        return ApiResponse::created($enrollment->load('course'), 'Enrolled successfully');
    }

    /**
     * All enrolled courses (active + completed) — used for the "Courses" tab on the student profile.
     * Filter by ?status=active|completed to scope to in-progress or finished courses.
     */
    public function myCourses(Request $request): JsonResponse
    {
        $query = $request->user()
            ->enrolledCourses()
            ->with(['course' => fn($q) => $q->with(['teacher', 'category', 'level'])])
            ->whereIn('status', ['active', 'completed'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $enrollments = $query->get()->map(fn($e) => [
            'enrollment_id'    => $e->id,
            'enrollment_status'=> $e->status,
            'progress_percent' => $e->progress_percent,
            'enrolled_at'      => $e->created_at,
            'completed_at'     => $e->completed_at,
            'course' => [
                'id'        => $e->course?->id,
                'title'     => $e->course?->title,
                'price'     => $e->course?->price,
                'is_free'   => ($e->course?->price ?? 0) == 0,
                'duration'  => $e->course?->duration,
                'thumbnail' => $e->course?->thumbnail
                    ? asset('storage/' . $e->course->thumbnail)
                    : null,
                'rating'    => $e->course?->rating,
                'category'  => $e->course?->category?->name,
                'level'     => $e->course?->level?->name,
                'teacher' => $e->course?->teacher ? [
                    'id'        => $e->course->teacher->id,
                    'name'      => $e->course->teacher->name,
                    'photo_url' => $e->course->teacher->photo_url,
                    'rating'    => $e->course->teacher->teacherProfile?->rating,
                ] : null,
            ],
        ]);

        return ApiResponse::success($enrollments);
    }

    public function completedCourses(Request $request): JsonResponse
    {
        $enrollments = $request->user()
            ->enrolledCourses()
            ->with(['course' => fn($q) => $q->with(['teacher', 'category', 'level'])])
            ->where('status', 'completed')
            ->latest()
            ->get()
            ->map(fn($e) => [
                'enrollment_id'    => $e->id,
                'progress_percent' => $e->progress_percent,
                'completed_at'     => $e->completed_at,
                'course' => [
                    'id'        => $e->course?->id,
                    'title'     => $e->course?->title,
                    'price'     => $e->course?->price,
                    'is_free'   => ($e->course?->price ?? 0) == 0,
                    'duration'  => $e->course?->duration,
                    'thumbnail' => $e->course?->thumbnail
                        ? asset('storage/' . $e->course->thumbnail)
                        : null,
                    'rating'    => $e->course?->rating,
                    'teacher' => $e->course?->teacher ? [
                        'id'        => $e->course->teacher->id,
                        'name'      => $e->course->teacher->name,
                        'photo_url' => $e->course->teacher->photo_url,
                        'rating'    => $e->course->teacher->teacherProfile?->rating,
                    ] : null,
                ],
            ]);

        return ApiResponse::success($enrollments);
    }

    // ─── Video Progress ───────────────────────────────────────────

    /**
     * Called by the client periodically as the video plays.
     * Tracks watch position. If >= COMPLETION_THRESHOLD% → auto-completes the lesson.
     * Enforces sequential order: previous lesson must be completed first.
     *
     * Body: { watched_seconds, total_seconds, last_position_seconds }
     */
    public function updateVideoProgress(int $courseId, int $lessonId, Request $request): JsonResponse
    {
        $enrollment = $this->getActiveEnrollment($request, $courseId);
        if (!$enrollment) {
            return ApiResponse::error('You are not enrolled in this course.', 403);
        }

        $lesson = Lesson::where('course_id', $courseId)->find($lessonId);
        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found.');
        }

        // Sequential lock: check previous lesson
        if ($lesson->order > 1) {
            $prevLesson = Lesson::where('course_id', $courseId)
                ->where('order', $lesson->order - 1)
                ->first();

            if ($prevLesson) {
                $prevProgress = LessonVideoProgress::where('enrollment_id', $enrollment->id)
                    ->where('lesson_id', $prevLesson->id)
                    ->first();

                if (!$prevProgress || !$prevProgress->is_completed) {
                    return ApiResponse::error(
                        'Complete the previous lesson video before accessing this one.',
                        403,
                        ['locked_by_lesson_id' => $prevLesson->id]
                    );
                }
            }
        }

        $request->validate([
            'watched_seconds'       => 'required|integer|min:0',
            'total_seconds'         => 'required|integer|min:1',
            'last_position_seconds' => 'sometimes|integer|min:0',
        ]);

        $watched    = $request->watched_seconds;
        $total      = $request->total_seconds;
        $percentage = min(100, round(($watched / $total) * 100, 2));

        $progress = LessonVideoProgress::updateOrCreate(
            ['enrollment_id' => $enrollment->id, 'lesson_id' => $lessonId],
            [
                'watched_seconds'       => max($watched, 0),
                'total_seconds'         => $total,
                'percentage_watched'    => $percentage,
                'last_position_seconds' => $request->last_position_seconds ?? 0,
            ]
        );

        $justCompleted = false;

        if ($percentage >= self::COMPLETION_THRESHOLD && !$progress->is_completed) {
            $progress->update([
                'is_completed' => true,
                'completed_at' => now(),
            ]);
            $justCompleted = true;

            // Update enrollment's completed lesson list
            $completedIds = $enrollment->progress ?? [];
            if (!in_array($lessonId, $completedIds)) {
                $completedIds[] = $lessonId;
                $totalLessons   = Lesson::where('course_id', $courseId)->count();
                $percent        = $totalLessons > 0 ? round((count($completedIds) / $totalLessons) * 100) : 0;

                $enrollment->update([
                    'progress'         => $completedIds,
                    'progress_percent' => $percent,
                ]);

                // Check full course completion
                if (count($completedIds) >= $totalLessons) {
                    $this->completeCourse($enrollment, $request->user());
                }
            }
        }

        $nextLesson = Lesson::where('course_id', $courseId)
            ->where('order', $lesson->order + 1)
            ->first();

        return ApiResponse::success([
            'lesson_id'          => $lessonId,
            'percentage_watched' => $percentage,
            'is_completed'       => $progress->is_completed || $justCompleted,
            'just_completed'     => $justCompleted,
            'next_lesson_id'     => $nextLesson?->id,
            'next_lesson_locked' => $nextLesson && !($progress->is_completed || $justCompleted),
        ], $justCompleted ? 'Lesson completed!' : 'Progress saved.');
    }

    // ─── Course Progress ──────────────────────────────────────────

    public function courseProgress(int $courseId, Request $request): JsonResponse
    {
        $enrollment = $request->user()
            ->enrolledCourses()
            ->where('course_id', $courseId)
            ->first();

        if (!$enrollment) {
            return ApiResponse::error('You are not enrolled in this course.', 403);
        }

        $lessons      = Lesson::where('course_id', $courseId)->orderBy('order')->get();
        $completedIds = $enrollment->progress ?? [];
        $totalLessons = $lessons->count();

        // Fetch video progress for all lessons in one query
        $videoProgressMap = LessonVideoProgress::where('enrollment_id', $enrollment->id)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->get()
            ->keyBy('lesson_id');

        return ApiResponse::success([
            'enrollment_status' => $enrollment->status,
            'completed_lessons' => count($completedIds),
            'total_lessons'     => $totalLessons,
            'progress_percent'  => $totalLessons > 0
                ? round((count($completedIds) / $totalLessons) * 100) : 0,
            'lessons' => $lessons->map(function ($l) use ($completedIds, $videoProgressMap) {
                $vp = $videoProgressMap->get($l->id);
                return [
                    'id'                 => $l->id,
                    'title'              => $l->title,
                    'order'              => $l->order,
                    'duration'           => $l->duration,
                    'is_free'            => $l->is_free,
                    'video_status'       => $l->video_status,
                    'is_completed'       => in_array($l->id, $completedIds),
                    'percentage_watched' => $vp?->percentage_watched ?? 0,
                    'last_position'      => $vp?->last_position_seconds ?? 0,
                ];
            }),
        ]);
    }

    /**
     * Legacy endpoint: mark a lesson complete (requires video to be watched).
     */
    public function completeLesson(int $courseId, int $lessonId, Request $request): JsonResponse
    {
        $enrollment = $this->getActiveEnrollment($request, $courseId);
        if (!$enrollment) {
            return ApiResponse::error('You are not enrolled in this course.', 403);
        }

        $lesson = Lesson::where('course_id', $courseId)->find($lessonId);
        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found.');
        }

        // Require video to have been watched
        $vp = LessonVideoProgress::where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lessonId)
            ->first();

        if (!$vp || $vp->percentage_watched < self::COMPLETION_THRESHOLD) {
            return ApiResponse::error(
                'You must watch at least ' . self::COMPLETION_THRESHOLD . '% of the video to complete this lesson.',
                403,
                ['percentage_watched' => $vp?->percentage_watched ?? 0]
            );
        }

        $completedIds = $enrollment->progress ?? [];
        if (!in_array($lessonId, $completedIds)) {
            $completedIds[] = $lessonId;
            $totalLessons   = Lesson::where('course_id', $courseId)->count();
            $percent        = $totalLessons > 0 ? round((count($completedIds) / $totalLessons) * 100) : 0;

            $enrollment->update([
                'progress'         => $completedIds,
                'progress_percent' => $percent,
            ]);

            if (count($completedIds) >= $totalLessons) {
                $this->completeCourse($enrollment, $request->user());
            }
        }

        $totalLessons = Lesson::where('course_id', $courseId)->count();

        return ApiResponse::success([
            'completed_lessons' => count($completedIds),
            'total_lessons'     => $totalLessons,
            'progress_percent'  => $totalLessons > 0 ? round((count($completedIds) / $totalLessons) * 100) : 0,
            'course_completed'  => count($completedIds) >= $totalLessons,
        ], 'Lesson marked as completed.');
    }

    // ─── In-Progress Courses ─────────────────────────────────────

    public function inProgressCourses(Request $request): JsonResponse
    {
        $enrollments = $request->user()
            ->enrolledCourses()
            ->with(['course' => fn($q) => $q->with(['teacher', 'category', 'level'])])
            ->where('status', 'active')
            ->where('progress_percent', '>', 0)
            ->latest()
            ->get()
            ->map(fn($e) => [
                'enrollment_id'     => $e->id,
                'enrollment_status' => $e->status,
                'progress_percent'  => $e->progress_percent,
                'enrolled_at'       => $e->created_at,
                'course' => [
                    'id'        => $e->course?->id,
                    'title'     => $e->course?->title,
                    'price'     => $e->course?->price,
                    'is_free'   => ($e->course?->price ?? 0) == 0,
                    'duration'  => $e->course?->duration,
                    'thumbnail' => $e->course?->thumbnail
                        ? asset('storage/' . $e->course->thumbnail)
                        : null,
                    'rating'    => $e->course?->rating,
                    'category'  => $e->course?->category?->name,
                    'level'     => $e->course?->level?->name,
                    'teacher'   => $e->course?->teacher ? [
                        'id'        => $e->course->teacher->id,
                        'name'      => $e->course->teacher->name,
                        'photo_url' => $e->course->teacher->photo_url,
                    ] : null,
                ],
            ]);

        return ApiResponse::success($enrollments);
    }

    // ─── Favorite Courses ─────────────────────────────────────────

    public function listFavorites(Request $request): JsonResponse
    {
        $favorites = CourseFavorite::where('student_id', $request->user()->id)
            ->with(['course' => fn($q) => $q->with(['teacher', 'category', 'level'])
                ->where('status', 'published')])
            ->get()
            ->filter(fn($f) => $f->course !== null)
            ->map(fn($f) => [
                'favorite_id' => $f->id,
                'course' => [
                    'id'        => $f->course->id,
                    'title'     => $f->course->title,
                    'price'     => $f->course->price,
                    'is_free'   => ($f->course->price ?? 0) == 0,
                    'duration'  => $f->course->duration,
                    'thumbnail' => $f->course->thumbnail
                        ? asset('storage/' . $f->course->thumbnail)
                        : null,
                    'rating'    => $f->course->rating,
                    'category'  => $f->course->category?->name,
                    'level'     => $f->course->level?->name,
                    'teacher'   => $f->course->teacher ? [
                        'id'        => $f->course->teacher->id,
                        'name'      => $f->course->teacher->name,
                        'photo_url' => $f->course->teacher->photo_url,
                    ] : null,
                ],
            ])
            ->values();

        return ApiResponse::success($favorites);
    }

    public function toggleFavorite(int $courseId, Request $request): JsonResponse
    {
        $course = Course::where('status', 'published')->find($courseId);
        if (!$course) {
            return ApiResponse::notFound('Course not found.');
        }

        $student  = $request->user();
        $existing = CourseFavorite::where('student_id', $student->id)
            ->where('course_id', $courseId)
            ->first();

        if ($existing) {
            $existing->delete();
            return ApiResponse::success(['favorited' => false], 'Removed from favorites.');
        }

        CourseFavorite::create([
            'student_id' => $student->id,
            'course_id'  => $courseId,
        ]);

        return ApiResponse::success(['favorited' => true], 'Added to favorites.');
    }

    // ─── Lesson Detail ────────────────────────────────────────────

    public function lessonDetail(int $courseId, int $lessonId, Request $request): JsonResponse
    {
        $user       = $request->user();
        $enrollment = $user->enrolledCourses()
            ->where('course_id', $courseId)
            ->first();

        $lesson = Lesson::with('attachments')
            ->where('course_id', $courseId)
            ->find($lessonId);

        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found.');
        }

        // Free lessons are accessible without enrollment
        if (!$enrollment && !$lesson->is_free) {
            return ApiResponse::error('You must enroll in this course to access this lesson.', 403);
        }

        // Video progress for enrolled students
        $videoProgress = null;
        if ($enrollment) {
            $vp = LessonVideoProgress::where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lessonId)
                ->first();
            if ($vp) {
                $videoProgress = [
                    'percentage_watched'    => $vp->percentage_watched,
                    'last_position_seconds' => $vp->last_position_seconds,
                    'is_completed'          => $vp->is_completed,
                    'watched_seconds'       => $vp->watched_seconds,
                    'total_seconds'         => $vp->total_seconds,
                ];
            }
        }

        // Sequential lock check
        $isLocked = false;
        if ($enrollment && $lesson->order > 1) {
            $completedIds = $enrollment->progress ?? [];
            $prevLesson   = Lesson::where('course_id', $courseId)
                ->where('order', $lesson->order - 1)
                ->first();
            if ($prevLesson && !in_array($prevLesson->id, $completedIds)) {
                $isLocked = true;
            }
        }

        return ApiResponse::success([
            'id'             => $lesson->id,
            'title'          => $lesson->title,
            'description'    => $lesson->description,
            'order'          => $lesson->order,
            'duration'       => $lesson->duration,
            'is_free'        => $lesson->is_free,
            'video_url'      => $lesson->video_url,
            'stream_url'     => $lesson->stream_url,
            'video_status'   => $lesson->video_status,
            'attachments'    => $lesson->attachments,
            'is_locked'      => $isLocked,
            'video_progress' => $videoProgress,
        ]);
    }

    // ─── Certificate ──────────────────────────────────────────────

    public function getCertificate(int $courseId, Request $request): JsonResponse
    {
        $enrollment = $request->user()
            ->enrolledCourses()
            ->where('course_id', $courseId)
            ->where('status', 'completed')
            ->first();

        if (!$enrollment) {
            return ApiResponse::error('Course not completed yet. Finish all lessons to earn your certificate.', 403);
        }

        $certificate = CourseCertificate::where('enrollment_id', $enrollment->id)->first();

        if (!$certificate) {
            // Issue certificate on first access
            $certificate = $this->issueCertificate($enrollment);
        }

        return ApiResponse::success($certificate->load('course', 'student'));
    }

    public function downloadCertificate(int $courseId, Request $request): Response
    {
        $enrollment = $request->user()
            ->enrolledCourses()
            ->where('course_id', $courseId)
            ->where('status', 'completed')
            ->first();

        abort_if(!$enrollment, 403, 'Course not completed.');

        $certificate = CourseCertificate::where('enrollment_id', $enrollment->id)->first()
            ?? $this->issueCertificate($enrollment);

        $course  = Course::with('teacher')->find($courseId);
        $student = $request->user();

        $pdf = Pdf::loadView('certificates.course', [
            'appName'           => config('app.name'),
            'studentName'       => $student->name,
            'courseTitle'       => $course->title,
            'teacherName'       => $course->teacher->name ?? 'Instructor',
            'issuedAt'          => $certificate->issued_at->format('F j, Y'),
            'certificateNumber' => $certificate->certificate_number,
        ])
        ->setPaper('a4', 'landscape');

        $filename = 'Certificate-' . Str::slug($course->title) . '-' . $student->id . '.pdf';

        return $pdf->download($filename);
    }

    // ─── Private Helpers ──────────────────────────────────────────

    private function getActiveEnrollment(Request $request, int $courseId): ?CourseEnrollment
    {
        return $request->user()
            ->enrolledCourses()
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->first();
    }

    private function completeCourse(CourseEnrollment $enrollment, $user): void
    {
        if ($enrollment->status !== 'completed') {
            $enrollment->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
            $user->studentProfile?->increment('courses_completed');

            // Notify student that their certificate is ready
            $course = Course::find($enrollment->course_id);
            AppNotification::create([
                'user_id' => $user->id,
                'title'   => 'Course Completed!',
                'body'    => 'Congratulations! You completed "' . ($course->title ?? 'the course') . '". Your certificate is ready to download.',
                'type'    => 'course_completed',
            ]);
        }

        // Auto-issue certificate
        $this->issueCertificate($enrollment);
    }

    private function issueCertificate(CourseEnrollment $enrollment): CourseCertificate
    {
        return CourseCertificate::firstOrCreate(
            ['enrollment_id' => $enrollment->id],
            [
                'student_id'         => $enrollment->student_id,
                'course_id'          => $enrollment->course_id,
                'certificate_number' => 'CERT-' . strtoupper(Str::random(4)) . '-' . $enrollment->id . '-' . date('Y'),
                'issued_at'          => now(),
            ]
        );
    }
}
