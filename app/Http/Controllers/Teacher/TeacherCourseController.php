<?php

namespace App\Http\Controllers\Teacher;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreCourseRequest;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherCourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courses = Course::where('teacher_id', $request->user()->id)
            ->withCount('enrollments')
            ->orderByDesc('created_at')
            ->paginate(10);

        return ApiResponse::success($courses);
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {

        $course = Course::create([
            'teacher_id'  => $request->user()->id,
            'title'       => $request->title,
            'description' => $request->description,
            'category'    => $request->category,
            'language'    => $request->language,
            'price'       => $request->price,
            'status'      => 'draft',
        ]);

        return ApiResponse::created($course, 'Course created');
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $course = Course::where('teacher_id', $request->user()->id)
            ->with(['lessons', 'enrollments.student.profile', 'reviews.fromUser.profile'])
            ->find($id);

        if (!$course) {
            return ApiResponse::notFound('Course not found');
        }

        return ApiResponse::success($course);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $course = Course::where('teacher_id', $request->user()->id)->find($id);

        if (!$course) {
            return ApiResponse::notFound('Course not found');
        }

        $request->validate([
            'title'        => 'sometimes|string|max:255',
            'description'  => 'sometimes|string',
            'category'     => 'sometimes|string',
            'language'     => 'sometimes|string',
            'price'        => 'sometimes|numeric|min:0',
            'platform_fee' => 'sometimes|numeric|min:0',
        ]);

        $course->update($request->only([
            'title', 'description', 'category', 'language',
            'price', 'platform_fee', 'duration',
        ]));

        return ApiResponse::success($course, 'Course updated');
    }

    public function publish(int $id, Request $request): JsonResponse
    {
        // Teacher must have an active subscription to publish courses
        if (!$request->user()->hasActiveSubscription()) {
            return ApiResponse::error(
                'You need an active subscription to publish courses. You can still create and edit drafts.',
                402,
                ['subscription_required' => true]
            );
        }

        $course = Course::where('teacher_id', $request->user()->id)->find($id);

        if (!$course) {
            return ApiResponse::notFound('Course not found');
        }

        if ($course->lessons()->count() === 0) {
            return ApiResponse::error('Add at least one lesson before publishing the course.');
        }

        $course->update(['status' => 'published']);

        return ApiResponse::success($course, 'Course published successfully.');
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $course = Course::where('teacher_id', $request->user()->id)->find($id);

        if (!$course) {
            return ApiResponse::notFound('Course not found');
        }

        $course->update(['status' => 'archived']);

        return ApiResponse::success(null, 'Course archived');
    }

    // ─── Lessons ─────────────────────────────────────────────────

    public function uploadThumbnail(int $id, Request $request): JsonResponse
    {
        $course = Course::where('teacher_id', $request->user()->id)->find($id);

        if (!$course) {
            return ApiResponse::notFound('Course not found');
        }

        $request->validate([
            'thumbnail' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $path = $request->file('thumbnail')->store('course-thumbnails', 'public');
        $course->update(['thumbnail' => $path]);

        return ApiResponse::success([
            'thumbnail_url' => asset('storage/' . $path),
        ], 'Thumbnail uploaded.');
    }

    public function storeLesson(int $courseId, Request $request): JsonResponse
    {
        $course = Course::where('teacher_id', $request->user()->id)->find($courseId);

        if (!$course) {
            return ApiResponse::notFound('Course not found');
        }

        $request->validate([
            'title'       => 'required|string|max:255',
            'video_url'   => 'sometimes|string',
            'order'       => 'required|integer|min:1',
            'description' => 'sometimes|string',
            'duration'    => 'sometimes|string',
            'is_free'     => 'sometimes|boolean',
        ]);

        $lesson = Lesson::create([
            'course_id'   => $course->id,
            'title'       => $request->title,
            'video_url'   => $request->video_url,
            'order'       => $request->order,
            'description' => $request->description,
            'duration'    => $request->duration,
            'is_free'     => $request->is_free ?? false,
        ]);

        return ApiResponse::created($lesson, 'Lesson added');
    }

    public function uploadLessonVideo(int $lessonId, Request $request): JsonResponse
    {
        $lesson = Lesson::whereHas('course', fn($q) => $q->where('teacher_id', $request->user()->id))->find($lessonId);

        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found');
        }

        $request->validate([
            'video' => 'required|file|mimes:mp4,mov,avi|max:512000',
        ]);

        $path = $request->file('video')->store('lesson-videos', 'public');
        $lesson->update(['video_url' => asset('storage/' . $path)]);

        return ApiResponse::success([
            'video_url' => asset('storage/' . $path),
        ], 'Lesson video uploaded.');
    }

    public function updateLesson(int $lessonId, Request $request): JsonResponse
    {
        $lesson = Lesson::whereHas('course', fn($q) => $q->where('teacher_id', $request->user()->id))->find($lessonId);

        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found');
        }

        $lesson->update($request->only(['title', 'video_url', 'order', 'description', 'duration', 'is_free']));

        return ApiResponse::success($lesson, 'Lesson updated');
    }

    public function deleteLesson(int $lessonId, Request $request): JsonResponse
    {
        $lesson = Lesson::whereHas('course', fn($q) => $q->where('teacher_id', $request->user()->id))->find($lessonId);

        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found');
        }

        $lesson->delete();

        return ApiResponse::success(null, 'Lesson deleted');
    }
}
