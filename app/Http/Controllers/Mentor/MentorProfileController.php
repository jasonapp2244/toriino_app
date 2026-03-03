<?php

namespace App\Http\Controllers\Mentor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Availability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['mentorProfile', 'availabilities']);

        return ApiResponse::success([
            'id'                      => $user->id,
            'full_name'               => $user->full_name ?? $user->name,
            'email'                   => $user->email,
            'phone'                   => $user->phone,
            'role'                    => $user->role,
            'language'                => $user->language,
            'has_active_subscription' => $user->hasActiveSubscription(),
            'profile'                 => $this->buildMentorProfile($user),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'full_name'              => 'sometimes|string|max:255',
            'phone'                  => 'sometimes|string|max:20',
            'language'               => 'sometimes|string|max:50',
            'timezone'               => 'sometimes|string|max:50',
            // mentor-specific
            'designation'            => 'sometimes|string|max:255',
            'short_bio'              => 'sometimes|string|max:1000',
            'specialization'         => 'sometimes|string|max:255',
            'industry'               => 'sometimes|string|max:100',
            'expertise_list'         => 'sometimes|array',
            'expertise_list.*'       => 'string|max:100',
            'preferred_student_level'=> 'sometimes|in:beginner,intermediate,advanced,all',
            'languages_list'         => 'sometimes|array',
            'languages_list.*'       => 'string|max:50',
            'price_per_hour'         => 'sometimes|numeric|min:0',
            'experience_years'       => 'sometimes|string|max:20',
            // availability
            'availability'           => 'sometimes|array',
            'availability.*.day'     => 'required_with:availability|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'availability.*.start_time' => 'required_with:availability|date_format:H:i',
            'availability.*.end_time'   => 'required_with:availability|date_format:H:i',
        ]);

        $user = $request->user();

        // Update user base fields
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

        // Update mentor profile
        $profileUpdates = array_filter([
            'designation'             => $request->designation,
            'short_bio'               => $request->short_bio,
            'specialization'          => $request->specialization,
            'industry'                => $request->industry,
            'expertise_list'          => $request->expertise_list,
            'preferred_student_level' => $request->preferred_student_level,
            'languages_list'          => $request->languages_list,
            'languages'               => $request->languages_list ? implode(', ', $request->languages_list) : null,
            'price_per_hour'          => $request->price_per_hour,
            'experience_years'        => $request->experience_years,
        ], fn($v) => !is_null($v));

        $mentorProfile = $user->mentorProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $profileUpdates
        );

        // Auto-mark setup complete when all required fields are present
        if ($mentorProfile->fresh()->isComplete()) {
            $mentorProfile->update(['profile_setup_complete' => true]);
        }

        // Update availability if provided
        if ($request->has('availability')) {
            foreach ($request->availability as $slot) {
                Availability::updateOrCreate(
                    ['mentor_id' => $user->id, 'day' => $slot['day']],
                    [
                        'start_time'   => $slot['start_time'],
                        'end_time'     => $slot['end_time'],
                        'is_available' => $slot['is_available'] ?? true,
                    ]
                );
            }
        }

        $user->refresh()->load(['mentorProfile', 'availabilities']);

        return ApiResponse::success([
            'profile'      => $this->buildMentorProfile($user),
            'availability' => $user->availabilities,
        ], 'Profile updated successfully.');
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $user = $request->user();
        $path = $request->file('photo')->store('mentor-photos', 'public');

        // Delete old photo if exists
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

        $path = $request->file('video')->store('mentor-intro-videos', 'public');

        $request->user()->mentorProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['intro_video' => $path]
        );

        return ApiResponse::success([
            'intro_video_url' => asset('storage/' . $path),
        ], 'Intro video uploaded.');
    }

    public function publicProfile(int $id): JsonResponse
    {
        $user = \App\Models\User::with(['mentorProfile', 'availabilities', 'reviewsReceived.fromUser'])
            ->whereHas('roles', fn($q) => $q->where('name', 'mentor'))
            ->find($id);

        if (!$user) {
            return ApiResponse::notFound('Mentor not found.');
        }

        return ApiResponse::success([
            'id'           => $user->id,
            'full_name'    => $user->full_name ?? $user->name,
            'profile'      => $this->buildMentorProfile($user),
            'availability' => $user->availabilities->where('is_available', true)->values(),
            'reviews'      => $user->reviewsReceived->take(10),
        ]);
    }

    private function buildMentorProfile(\App\Models\User $user): array
    {
        $mp = $user->mentorProfile;
        if (!$mp) {
            return [];
        }
        return [
            'designation'             => $mp->designation,
            'short_bio'               => $mp->short_bio ?? $mp->intro,
            'specialization'          => $mp->specialization,
            'industry'                => $mp->industry,
            'expertise_list'          => $mp->expertise_list ?? [],
            'preferred_student_level' => $mp->preferred_student_level,
            'price_per_hour'          => $mp->price_per_hour,
            'experience_years'        => $mp->experience_years,
            'languages_list'          => $mp->languages_list ?? [],
            'intro_video_url'         => $mp->intro_video_url,
            'rating'                  => $mp->rating,
            'total_reviews'           => $mp->total_reviews,
            'is_verified'             => $mp->is_verified,
            'is_featured'             => $mp->is_featured,
        ];
    }
}
