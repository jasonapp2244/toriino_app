<?php

namespace App\Http\Controllers\Teacher;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreCourseRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Services\MuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherCourseController extends Controller
{
    private const MIN_LESSONS = 5;
    private const MAX_LESSONS = 10;

    public function index(Request $request): JsonResponse
    {
        $courses = Course::where('teacher_id', $request->user()->id)
            ->withCount('enrollments')
            ->orderByDesc('created_at')
            ->paginate(10);

        return ApiResponse::success($courses);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'language'    => 'required|string|max:50',
            'price'       => 'required|numeric|min:0',
        ]);

        // Resolve category_id from name if not provided as ID
        $categoryId = $request->category_id;
        if (!$categoryId && $request->category) {
            $cat = \App\Models\CourseCategory::where('name', $request->category)->first();
            $categoryId = $cat?->id;
        }

        // Resolve level_id from name if not provided as ID
        $levelId = $request->level_id;
        if (!$levelId && $request->level) {
            $lvl = \App\Models\CourseLevel::where('name', $request->level)->first();
            $levelId = $lvl?->id;
        }

        $course = Course::create([
            'teacher_id'  => $request->user()->id,
            'title'       => $request->title,
            'description' => $request->description,
            'category_id' => $categoryId,
            'level_id'    => $levelId,
            'language'    => $request->language,
            'price'       => $request->price,
            'status'      => 'draft',
        ]);

        return ApiResponse::created($course, 'Course created');
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $course = Course::where('teacher_id', $request->user()->id)
            ->with(['category', 'level', 'lessons.attachments', 'enrollments.student.profile', 'reviews.fromUser.profile'])
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
            'category_id'  => 'sometimes|exists:course_categories,id',
            'level_id'     => 'sometimes|exists:course_levels,id',
            'language'     => 'sometimes|string',
            'price'        => 'sometimes|numeric|min:0',
        ]);

        $course->update($request->only([
            'title', 'description', 'category_id', 'level_id',
            'language', 'price', 'duration',
        ]));

        return ApiResponse::success($course, 'Course updated');
    }

    public function publish(int $id, Request $request): JsonResponse
    {
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

        $lessonCount = $course->lessons()->count();

        if ($lessonCount < self::MIN_LESSONS) {
            return ApiResponse::error(
                "A course must have at least " . self::MIN_LESSONS . " lessons before publishing. Current: {$lessonCount}."
            );
        }

        // Every lesson must have a video ready
        $missingVideo = $course->lessons()
            ->where(function ($q) {
                $q->whereNull('mux_playback_id')
                  ->whereNull('video_url');
            })
            ->count();

        if ($missingVideo > 0) {
            return ApiResponse::error("{$missingVideo} lesson(s) are missing a video. Every lesson must have a video.");
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

    // ─── Thumbnail ────────────────────────────────────────────────

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

    // ─── Lessons ──────────────────────────────────────────────────

    public function storeLesson(int $courseId, Request $request): JsonResponse
    {
        $course = Course::where('teacher_id', $request->user()->id)->find($courseId);

        if (!$course) {
            return ApiResponse::notFound('Course not found');
        }

        $lessonCount = $course->lessons()->count();
        if ($lessonCount >= self::MAX_LESSONS) {
            return ApiResponse::error(
                "A course cannot have more than " . self::MAX_LESSONS . " lessons. Current: {$lessonCount}."
            );
        }

        $request->validate([
            'title'       => 'required|string|max:255',
            'order'       => 'required|integer|min:1',
            'description' => 'sometimes|string',
            'duration'    => 'sometimes|string',
            'is_free'     => 'sometimes|boolean',
        ]);

        $lesson = Lesson::create([
            'course_id'   => $course->id,
            'title'       => $request->title,
            'order'       => $request->order,
            'description' => $request->description,
            'duration'    => $request->duration,
            'is_free'     => $request->is_free ?? false,
        ]);

        return ApiResponse::created($lesson, 'Lesson added. Upload a video using the Mux upload endpoint.');
    }

    public function updateLesson(int $lessonId, Request $request): JsonResponse
    {
        $lesson = Lesson::whereHas('course', fn($q) => $q->where('teacher_id', $request->user()->id))->find($lessonId);

        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found');
        }

        $lesson->update($request->only(['title', 'order', 'description', 'duration', 'is_free']));

        return ApiResponse::success($lesson, 'Lesson updated');
    }

    public function deleteLesson(int $lessonId, Request $request): JsonResponse
    {
        $lesson = Lesson::whereHas('course', fn($q) => $q->where('teacher_id', $request->user()->id))->find($lessonId);

        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found');
        }

        // Delete Mux asset if it exists
        if ($lesson->mux_asset_id) {
            try {
                app(MuxService::class)->deleteAsset($lesson->mux_asset_id);
            } catch (\Throwable) {
                // Non-fatal — proceed with local deletion
            }
        }

        $lesson->delete();

        return ApiResponse::success(null, 'Lesson deleted');
    }

    // ─── Mux Video Upload ─────────────────────────────────────────

    /**
     * Step 1: Teacher requests a Mux direct upload URL.
     * The client then uploads the video file directly to Mux (not through our server).
     * Step 2: Mux processes the video and notifies via webhook.
     */
    public function initMuxUpload(int $lessonId, Request $request): JsonResponse
    {
        $lesson = Lesson::whereHas('course', fn($q) => $q->where('teacher_id', $request->user()->id))->find($lessonId);

        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found');
        }

        try {
            $result = app(MuxService::class)->createDirectUpload();

            $lesson->update([
                'mux_upload_id' => $result['upload_id'],
                'video_status'  => 'pending',
            ]);

            return ApiResponse::success([
                'upload_url'  => $result['upload_url'],
                'upload_id'   => $result['upload_id'],
                'lesson_id'   => $lesson->id,
                'instructions'=> 'PUT the video file directly to upload_url. Mux will process it and notify via webhook.',
            ], 'Mux upload URL generated.');
        } catch (\Throwable $e) {
            \Log::error('Mux upload initialization failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Video upload failed. Please try again later.', 500);
        }
    }

    /**
     * Fallback: Upload video directly to our server (for dev / non-Mux setups).
     */
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
        $lesson->update([
            'video_url'    => asset('storage/' . $path),
            'video_status' => 'ready',
        ]);

        return ApiResponse::success([
            'video_url' => asset('storage/' . $path),
        ], 'Lesson video uploaded.');
    }

    // ─── Lesson Attachments ───────────────────────────────────────

    public function addLessonAttachment(int $lessonId, Request $request): JsonResponse
    {
        $lesson = Lesson::whereHas('course', fn($q) => $q->where('teacher_id', $request->user()->id))->find($lessonId);

        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found');
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,ppt,pptx,jpg,jpeg,png,webp|max:20480',
            'type' => 'required|in:pdf,ppt,image',
        ]);

        $file = $request->file('file');
        $path = $file->store('lesson-attachments', 'public');

        $attachment = LessonAttachment::create([
            'lesson_id'     => $lesson->id,
            'type'          => $request->type,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_size_kb'  => (int) round($file->getSize() / 1024),
        ]);

        return ApiResponse::created($attachment, 'Attachment added to lesson.');
    }

    public function deleteLessonAttachment(int $lessonId, int $attachmentId, Request $request): JsonResponse
    {
        $lesson = Lesson::whereHas('course', fn($q) => $q->where('teacher_id', $request->user()->id))->find($lessonId);

        if (!$lesson) {
            return ApiResponse::notFound('Lesson not found');
        }

        $attachment = LessonAttachment::where('lesson_id', $lessonId)->find($attachmentId);

        if (!$attachment) {
            return ApiResponse::notFound('Attachment not found');
        }

        \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return ApiResponse::success(null, 'Attachment deleted.');
    }
}
