<?php

namespace App\Http\Controllers\Student;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CourseCertificate;
use App\Models\CourseEnrollment;
use App\Models\SessionBooking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentProfileController extends Controller
{
    // ─── Own Profile ──────────────────────────────────────────────

    public function show(Request $request): JsonResponse
    {
        $user   = $request->user()->load(['studentProfile']);
        $userId = $user->id;

        $coursesInProgress = CourseEnrollment::where('student_id', $userId)
            ->where('status', 'active')
            ->count();

        $sessionsBooked = SessionBooking::where('student_id', $userId)->count();

        $certificatesEarned = CourseCertificate::where('student_id', $userId)->count();

        // Keep profile counter in sync
        if ($user->studentProfile && $user->studentProfile->certificates_earned !== $certificatesEarned) {
            $user->studentProfile->update(['certificates_earned' => $certificatesEarned]);
        }

        return ApiResponse::success([
            'id'        => $user->id,
            'name'      => $user->name,
            'full_name' => $user->full_name ?? $user->name,
            'email'     => $user->email,
            'phone'     => $user->phone,
            'photo_url' => $user->photo_url,
            'role'      => $user->role,
            'language'  => $user->language,
            'timezone'  => $user->timezone,
            'stats' => [
                'courses_in_progress' => $coursesInProgress,
                'sessions_booked'     => $sessionsBooked,
                'certificates_earned' => $certificatesEarned,
            ],
            'profile' => $user->studentProfile ? [
                'bio'             => $user->studentProfile->bio,
                'education_level' => $user->studentProfile->education_level,
                'language'        => $user->studentProfile->language,
                'interests'       => $user->studentProfile->interests,
                'image_url'       => $user->studentProfile->image_url,
            ] : null,
        ]);
    }

    // ─── My Certificates ──────────────────────────────────────────

    public function myCertificates(Request $request): JsonResponse
    {
        $certificates = CourseCertificate::where('student_id', $request->user()->id)
            ->with(['course.teacher'])
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn($cert) => [
                'id'                 => $cert->id,
                'certificate_number' => $cert->certificate_number,
                'course_id'          => $cert->course_id,
                'course_title'       => $cert->course?->title,
                'course_thumbnail'   => $cert->course?->thumbnail
                    ? asset('storage/' . $cert->course->thumbnail)
                    : null,
                'teacher_name'       => $cert->course?->teacher?->name,
                'issued_at'          => $cert->issued_at?->format('j M Y'),
                'download_url'       => $cert->download_url,
            ]);

        return ApiResponse::success($certificates);
    }

    // ─── Public Profile ───────────────────────────────────────────

    public function publicProfile(int $id): JsonResponse
    {
        $user = User::with(['studentProfile'])
            ->whereHas('roles', fn($q) => $q->where('name', 'student'))
            ->find($id);

        if (!$user) {
            return ApiResponse::notFound('Student not found.');
        }

        $coursesInProgress  = CourseEnrollment::where('student_id', $id)->where('status', 'active')->count();
        $sessionsBooked     = SessionBooking::where('student_id', $id)->count();
        $certificatesEarned = CourseCertificate::where('student_id', $id)->count();

        // Enrolled courses (for Courses tab on public profile)
        $enrolledCourses = CourseEnrollment::where('student_id', $id)
            ->with(['course' => fn($q) => $q->with(['teacher', 'category', 'level'])])
            ->latest()
            ->get()
            ->map(fn($e) => [
                'course_id'        => $e->course_id,
                'title'            => $e->course?->title,
                'price'            => $e->course?->price,
                'is_free'          => ($e->course?->price ?? 0) == 0,
                'duration'         => $e->course?->duration,
                'thumbnail'        => $e->course?->thumbnail
                    ? asset('storage/' . $e->course->thumbnail)
                    : null,
                'category'         => $e->course?->category?->name,
                'level'            => $e->course?->level?->name,
                'rating'           => $e->course?->rating,
                'enrollment_status'=> $e->status,
                'progress_percent' => $e->progress_percent,
                'teacher' => $e->course?->teacher ? [
                    'id'        => $e->course->teacher->id,
                    'name'      => $e->course->teacher->name,
                    'photo_url' => $e->course->teacher->photo_url,
                    'rating'    => $e->course->teacher->teacherProfile?->rating,
                ] : null,
            ]);

        // Certificates (for Certificates tab on public profile)
        $certificates = CourseCertificate::where('student_id', $id)
            ->with('course')
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn($cert) => [
                'id'                 => $cert->id,
                'certificate_number' => $cert->certificate_number,
                'course_title'       => $cert->course?->title,
                'course_thumbnail'   => $cert->course?->thumbnail
                    ? asset('storage/' . $cert->course->thumbnail)
                    : null,
                'issued_at'          => $cert->issued_at?->format('j M Y'),
                'download_url'       => $cert->download_url,
            ]);

        return ApiResponse::success([
            'id'        => $user->id,
            'name'      => $user->name,
            'full_name' => $user->full_name ?? $user->name,
            'photo_url' => $user->photo_url,
            'stats' => [
                'courses_in_progress' => $coursesInProgress,
                'sessions_booked'     => $sessionsBooked,
                'certificates_earned' => $certificatesEarned,
            ],
            'profile' => $user->studentProfile ? [
                'bio'             => $user->studentProfile->bio,
                'education_level' => $user->studentProfile->education_level,
                'language'        => $user->studentProfile->language,
                'interests'       => $user->studentProfile->interests,
                'image_url'       => $user->studentProfile->image_url,
            ] : null,
            'enrolled_courses' => $enrolledCourses,
            'certificates'     => $certificates,
        ]);
    }

    // ─── Update Profile ───────────────────────────────────────────

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'full_name'        => 'sometimes|string|max:255',
            'phone'            => 'sometimes|string|max:20',
            'language'         => 'sometimes|string|max:50',
            'timezone'         => 'sometimes|string|max:50',
            'bio'              => 'sometimes|string|max:500',
            'education_level'  => 'sometimes|string|max:100',
            'interests'        => 'sometimes|string|max:500',
            'profile_language' => 'sometimes|string|max:50',
        ]);

        $user = $request->user();

        if ($request->has('full_name')) {
            $user->update([
                'full_name' => $request->full_name,
                'name'      => $request->full_name,
            ]);
        }

        if ($request->has('phone'))    $user->update(['phone' => $request->phone]);
        if ($request->has('language')) $user->update(['language' => $request->language]);
        if ($request->has('timezone')) $user->update(['timezone' => $request->timezone]);

        $user->studentProfile()->updateOrCreate(
            ['user_id' => $user->id],
            array_filter([
                'bio'             => $request->bio,
                'education_level' => $request->education_level,
                'interests'       => $request->interests,
                'language'        => $request->profile_language ?? $request->language,
            ], fn($v) => !is_null($v))
        );

        return ApiResponse::success(
            $user->fresh()->load(['studentProfile']),
            'Profile updated successfully.'
        );
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        $path = $request->file('image')->store('student-photos', 'public');

        $request->user()->studentProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['image' => $path]
        );

        return ApiResponse::success([
            'image_url' => asset('storage/' . $path),
        ], 'Profile photo uploaded.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'          => 'required|string',
            'new_password'              => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return ApiResponse::error('Current password is incorrect.', 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->delete();

        return ApiResponse::success(null, 'Password changed. Please login again.');
    }
}
