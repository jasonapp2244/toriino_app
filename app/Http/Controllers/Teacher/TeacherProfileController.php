<?php

namespace App\Http\Controllers\Teacher;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['teacherProfile']);

        return ApiResponse::success([
            'id'                      => $user->id,
            'full_name'               => $user->full_name ?? $user->name,
            'email'                   => $user->email,
            'phone'                   => $user->phone,
            'role'                    => $user->role,
            'language'                => $user->language,
            'has_active_subscription' => $user->hasActiveSubscription(),
            'profile'                 => $this->buildTeacherProfile($user),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'full_name'        => 'sometimes|string|max:255',
            'phone'            => 'sometimes|string|max:20',
            'language'         => 'sometimes|string|max:50',
            'timezone'         => 'sometimes|string|max:50',
            // teacher-specific
            'designation'      => 'sometimes|string|max:255',
            'subject'          => 'sometimes|string|max:255',
            'subject_field'    => 'sometimes|string|max:255',
            'short_bio'        => 'sometimes|string|max:1000',
            'degree'           => 'sometimes|string|max:255',
            'experience_years' => 'sometimes|string|max:20',
            'expertise_list'   => 'sometimes|array',
            'expertise_list.*' => 'string|max:100',
            'languages_list'   => 'sometimes|array',
            'languages_list.*' => 'string|max:50',
        ]);

        $user = $request->user();

        $userUpdates = array_filter([
            'full_name' => $request->full_name,
            'name'      => $request->full_name,
            'phone'     => $request->phone,
            'language'  => $request->language,
            'timezone'  => $request->timezone,
        ], fn($v) => !is_null($v));

        if ($userUpdates) {
            $user->update($userUpdates);
        }

        $profileUpdates = array_filter([
            'designation'      => $request->designation,
            'subject'          => $request->subject,
            'subject_field'    => $request->subject_field,
            'short_bio'        => $request->short_bio,
            'degree'           => $request->degree,
            'experience_years' => $request->experience_years,
            'expertise_list'   => $request->expertise_list,
            'languages_list'   => $request->languages_list,
            'languages'        => $request->languages_list ? implode(', ', $request->languages_list) : null,
        ], fn($v) => !is_null($v));

        $teacherProfile = $user->teacherProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $profileUpdates
        );

        // Auto-mark setup complete when all required fields are present
        if ($teacherProfile->fresh()->isComplete()) {
            $teacherProfile->update(['profile_setup_complete' => true]);
        }

        return ApiResponse::success(
            $this->buildTeacherProfile($user->fresh()->load('teacherProfile')),
            'Profile updated successfully.'
        );
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $user = $request->user();
        $path = $request->file('photo')->store('teacher-photos', 'public');

        if ($user->profile && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile);
        }

        $user->update(['profile' => $path]);

        return ApiResponse::success([
            'photo_url' => asset('storage/' . $path),
        ], 'Profile photo uploaded.');
    }

    public function uploadIntroVideo(Request $request): JsonResponse
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,mov,avi|max:102400',
        ]);

        $path = $request->file('video')->store('teacher-intro-videos', 'public');

        $request->user()->teacherProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['intro_video' => $path]
        );

        return ApiResponse::success([
            'intro_video_url' => asset('storage/' . $path),
        ], 'Intro video uploaded.');
    }

    /**
     * GET /teachers/{id}
     * Public teacher profile viewed by students.
     *
     * Query params:
     *   ?sort=latest|popular|price_asc|price_desc  (default: latest)
     *   ?reviews_limit=N                           (default: 5)
     */
    public function publicProfile(int $id, Request $request): JsonResponse
    {
        $sort = $request->query('sort', 'latest');

        $coursesQuery = fn($q) => $q
            ->where('status', 'published')
            ->with(['category', 'level'])
            ->when($sort === 'popular',    fn($q) => $q->orderByDesc('total_enrollments'))
            ->when($sort === 'price_asc',  fn($q) => $q->orderBy('price'))
            ->when($sort === 'price_desc', fn($q) => $q->orderByDesc('price'))
            ->when(!in_array($sort, ['popular', 'price_asc', 'price_desc']), fn($q) => $q->latest());

        $user = \App\Models\User::with([
            'teacherProfile',
            'courses'              => $coursesQuery,
            'reviewsReceived.fromUser.profile',
        ])
            ->whereHas('roles', fn($q) => $q->where('name', 'teacher'))
            ->find($id);

        if (!$user) {
            return ApiResponse::notFound('Teacher not found.');
        }

        $reviewsLimit = min((int) $request->query('reviews_limit', 5), 20);

        $courses = $user->courses->map(fn($c) => [
            'id'               => $c->id,
            'title'            => $c->title,
            'description'      => $c->description,
            'price'            => $c->price,
            'is_free'          => ($c->price ?? 0) == 0,
            'is_live'          => (bool) $c->is_live,
            'duration'         => $c->duration,
            'language'         => $c->language,
            'rating'           => $c->rating,
            'total_enrollments'=> $c->total_enrollments,
            'thumbnail'        => $c->thumbnail ? asset('storage/' . $c->thumbnail) : null,
            'category'         => $c->category?->name,
            'level'            => $c->level?->name,
            'teacher' => [
                'id'        => $user->id,
                'name'      => $user->name,
                'photo_url' => $user->photo_url,
                'rating'    => $user->teacherProfile?->rating,
            ],
        ]);

        $reviews = $user->reviewsReceived->take($reviewsLimit)->map(fn($r) => [
            'id'         => $r->id,
            'rating'     => $r->rating,
            'comment'    => $r->comment,
            'created_at' => $r->created_at,
            'reviewer' => [
                'id'        => $r->fromUser?->id,
                'name'      => $r->fromUser?->name,
                'photo_url' => $r->fromUser?->photo_url,
            ],
        ]);

        return ApiResponse::success([
            'id'           => $user->id,
            'full_name'    => $user->full_name ?? $user->name,
            'photo_url'    => $user->photo_url,
            'profile'      => $this->buildTeacherProfile($user),
            'courses'      => $courses,
            'courses_count'=> $user->courses->count(),
            'reviews'      => $reviews,
            'total_reviews'=> $user->reviewsReceived->count(),
            'sort'         => $sort,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'          => 'required|string',
            'new_password'              => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required',
        ]);

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return ApiResponse::error('Current password is incorrect.', 422);
        }

        $user->update(['password' => \Illuminate\Support\Facades\Hash::make($request->new_password)]);
        $user->tokens()->delete();

        return ApiResponse::success(null, 'Password changed. Please login again.');
    }

    private function buildTeacherProfile(\App\Models\User $user): array
    {
        $tp = $user->teacherProfile;
        if (!$tp) {
            return [];
        }
        return [
            'designation'      => $tp->designation,
            'subject'          => $tp->subject,
            'subject_field'    => $tp->subject_field,
            'short_bio'        => $tp->short_bio ?? $tp->intro,
            'degree'           => $tp->degree,
            'experience_years' => $tp->experience_years,
            'expertise_list'   => $tp->expertise_list ?? [],
            'languages_list'   => $tp->languages_list ?? [],
            'intro_video_url'  => $tp->intro_video_url,
            'rating'           => $tp->rating,
            'total_reviews'    => $tp->total_reviews,
            'is_verified'      => $tp->is_verified,
        ];
    }
}
