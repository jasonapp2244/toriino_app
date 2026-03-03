<?php

namespace App\Http\Controllers\Student;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'studentProfile']);

        return ApiResponse::success([
            'id'         => $user->id,
            'full_name'  => $user->full_name ?? $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'language'   => $user->language,
            'timezone'   => $user->timezone,
            'profile'    => $user->studentProfile ? [
                'student_id'          => $user->studentProfile->student_id,
                'image'               => $user->studentProfile->image_url,
                'bio'                 => $user->studentProfile->bio,
                'language'            => $user->studentProfile->language,
                'education_level'     => $user->studentProfile->education_level,
                'interests'           => $user->studentProfile->interests,
                'courses_completed'   => $user->studentProfile->courses_completed,
                'certificates_earned' => $user->studentProfile->certificates_earned,
            ] : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'full_name'       => 'sometimes|string|max:255',
            'phone'           => 'sometimes|string|max:20',
            'language'        => 'sometimes|string|max:50',
            'timezone'        => 'sometimes|string|max:50',
            // student profile fields
            'bio'             => 'sometimes|string|max:500',
            'education_level' => 'sometimes|string|max:100',
            'interests'       => 'sometimes|string|max:500',
            'profile_language'=> 'sometimes|string|max:50',
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
            $user->fresh()->load(['profile', 'studentProfile']),
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

    public function publicProfile(int $id): JsonResponse
    {
        $user = \App\Models\User::with(['studentProfile'])
            ->whereHas('roles', fn($q) => $q->where('name', 'student'))
            ->find($id);

        if (!$user) {
            return ApiResponse::notFound('Student not found.');
        }

        return ApiResponse::success([
            'id'        => $user->id,
            'full_name' => $user->full_name ?? $user->name,
            'profile'   => $user->studentProfile,
        ]);
    }
}
