<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Jobs\SendForgotPasswordEmailJob;
use App\Jobs\SendOtpEmailJob;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // REGISTER  → sends OTP, user is NOT verified yet
    // ─────────────────────────────────────────────────────────────
    public function register(RegisterRequest $request): JsonResponse
    {
        $existing = User::where('email', $request->email)->first();

        if ($existing && $existing->is_verified) {
            return ApiResponse::error('Email already registered.', 409);
        }

        // If unverified record exists, reuse it
        $user = $existing ?? User::create([
            'full_name' => $request->full_name,
            'name'      => $request->full_name,
            'email'     => $request->email,
            'phone'     => $request->phone,
            'password'  => Hash::make($request->password),
            'role'      => $request->role ?? 'student',
            'status'    => 'active',
            'timezone'  => $request->timezone  ?? 'UTC',
            'language'  => $request->language  ?? 'en',
            'device_id'   => $request->device_id,
            'device_type' => $request->device_type,
            'fcm_token'   => $request->fcm_token,
        ]);

        if ($existing) {
            $existing->update([
                'full_name' => $request->full_name,
                'name'      => $request->full_name,
                'password'  => Hash::make($request->password),
                'role'      => $request->role ?? $existing->role,
            ]);
            $user = $existing->fresh();
        }

        $otp = $user->generateOtp();

        SendOtpEmailJob::dispatch($user, $otp, false);

        return ApiResponse::created([
            'email'   => $user->email,
            'message' => 'OTP sent to your email. Valid for 10 minutes.',
            'otp_dev' => app()->environment('local') ? $otp : null,
        ], 'Registration initiated. Please verify OTP.');
    }

    // ─────────────────────────────────────────────────────────────
    // OTP VERIFY  → marks user verified, assigns role, returns token
    // ─────────────────────────────────────────────────────────────
    public function otpVerify(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email|exists:users,email',
            'otp_code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return ApiResponse::notFound('User not found');
        }

        if ($user->is_verified) {
            return ApiResponse::error('Account is already verified.');
        }

        if (!$user->isOtpValid($request->otp_code)) {
            return ApiResponse::error('Invalid or expired OTP.', 422);
        }

        $user->update(['is_verified' => true, 'email_verified_at' => now()]);
        $user->clearOtp();

        // Always sync Spatie role to match users.role column.
        // Using syncRoles() ensures re-registration with a different role
        // (e.g., mentor → teacher) is handled correctly.
        $role = $user->role ?? 'student';
        $user->syncRoles([$role]);

        // Auto-create role-specific profile
        $this->createRoleProfile($user, $role);

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'user'  => $this->userResponse($user),
            'token' => $token,
        ], 'Account verified successfully.');
    }

    // ─────────────────────────────────────────────────────────────
    // RESEND OTP
    // ─────────────────────────────────────────────────────────────
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->is_verified) {
            return ApiResponse::error('Account is already verified.');
        }

        // Rate-limit: only allow resend after 60 seconds since the last OTP was sent.
        // otp_expires_at = sent_at + 10 min, so sent_at = otp_expires_at - 10 min.
        if ($user->otp_expires_at && $user->otp_expires_at->isFuture()) {
            $sentAt       = $user->otp_expires_at->copy()->subMinutes(10);
            $elapsed      = (int) $sentAt->diffInSeconds(now());
            $waitSeconds  = max(0, 60 - $elapsed);

            if ($waitSeconds > 0) {
                return ApiResponse::error(
                    "Please wait {$waitSeconds} second(s) before requesting a new OTP.",
                    429
                );
            }
        }

        $otp = $user->generateOtp();

        SendOtpEmailJob::dispatch($user, $otp, true);

        return ApiResponse::success([
            'otp_dev' => app()->environment('local') ? $otp : null,
        ], 'New OTP sent.');
    }

    // ─────────────────────────────────────────────────────────────
    // LOGIN  — role-based, checks verified + status
    // ─────────────────────────────────────────────────────────────
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return ApiResponse::error('Invalid email or password.', 401);
        }

        if (!$user->is_verified) {
            $otp = $user->generateOtp();
            SendOtpEmailJob::dispatch($user, $otp, true);
            return ApiResponse::error('Account not verified. A new OTP has been sent.', 403, [
                'require_otp' => true,
                'email'       => $user->email,
                'otp_dev'     => app()->environment('local') ? $otp : null,
            ]);
        }

        if ($user->isBanned()) {
            return ApiResponse::error('Your account has been suspended. Contact support.', 403);
        }

        if ($user->status === 'inactive') {
            return ApiResponse::error('Your account is inactive.', 403);
        }

        // Update device info & last active
        $user->update([
            'last_active_at' => now(),
            'fcm_token'      => $request->fcm_token   ?? $user->fcm_token,
            'device_id'      => $request->device_id   ?? $user->device_id,
            'device_type'    => $request->device_type ?? $user->device_type,
        ]);

        // Revoke old tokens, issue fresh one
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'user'  => $this->userResponse($user),
            'token' => $token,
        ], 'Login successful.');
    }

    // ─────────────────────────────────────────────────────────────
    // SOCIAL LOGIN  — Google / Apple
    // ─────────────────────────────────────────────────────────────
    public function socialLogin(Request $request): JsonResponse
    {
        $request->validate([
            'provider'    => 'required|in:google,apple',
            'provider_id' => 'required|string',
            'email'       => 'required|email',
            'full_name'   => 'nullable|string',
            'profile'     => 'nullable|string',    // photo URL from provider
            'role'        => 'required|in:student,mentor,teacher',
            'device_id'   => 'nullable|string',
            'device_type' => 'nullable|in:ios,android',
            'fcm_token'   => 'nullable|string',
        ]);

        // Find by provider_id or email
        $user = User::where('provider', $request->provider)
            ->where('provider_id', $request->provider_id)
            ->first()
            ?? User::where('email', $request->email)->first();

        if ($user) {
            // Existing user — update social info
            $user->update([
                'provider'       => $request->provider,
                'provider_id'    => $request->provider_id,
                'is_verified'    => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'last_active_at' => now(),
                'fcm_token'      => $request->fcm_token   ?? $user->fcm_token,
                'device_id'      => $request->device_id   ?? $user->device_id,
                'device_type'    => $request->device_type ?? $user->device_type,
            ]);
        } else {
            // New social user
            $user = User::create([
                'full_name'      => $request->full_name,
                'name'           => $request->full_name,
                'email'          => $request->email,
                'password'       => Hash::make(\Str::random(16)),
                'role'           => $request->role,
                'profile'        => $request->profile,
                'provider'       => $request->provider,
                'provider_id'    => $request->provider_id,
                'is_verified'    => true,
                'email_verified_at' => now(),
                'status'         => 'active',
                'last_active_at' => now(),
                'fcm_token'      => $request->fcm_token,
                'device_id'      => $request->device_id,
                'device_type'    => $request->device_type,
            ]);

            $user->assignRole($request->role);
            $this->createRoleProfile($user, $request->role);
        }

        if ($user->isBanned()) {
            return ApiResponse::error('Your account has been suspended.', 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'user'  => $this->userResponse($user),
            'token' => $token,
        ], 'Social login successful.');
    }

    // ─────────────────────────────────────────────────────────────
    // FORGOT PASSWORD  — sends OTP to email
    // ─────────────────────────────────────────────────────────────
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();
        $otp  = $user->generateOtp();

        SendForgotPasswordEmailJob::dispatch($user, $otp);

        return ApiResponse::success([
            'email'   => $user->email,
            'otp_dev' => app()->environment('local') ? $otp : null,
        ], 'Password reset OTP sent to your email.');
    }

    // ─────────────────────────────────────────────────────────────
    // RESET PASSWORD  — verify OTP then set new password
    // ─────────────────────────────────────────────────────────────
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'                 => 'required|email|exists:users,email',
            'otp_code'              => 'required|string|size:6',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user->isOtpValid($request->otp_code)) {
            return ApiResponse::error('Invalid or expired OTP.', 422);
        }

        $user->update(['password' => Hash::make($request->password)]);
        $user->clearOtp();
        $user->tokens()->delete();

        return ApiResponse::success(null, 'Password reset successfully. Please login.');
    }

    // ─────────────────────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    // ─────────────────────────────────────────────────────────────
    // ME  — current authenticated user with role-specific profile
    // ─────────────────────────────────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()
            ->load(['profile', 'mentorProfile', 'teacherProfile', 'studentProfile']);

        $user->update(['last_active_at' => now()]);

        return ApiResponse::success($this->userResponse($user));
    }

    // ─────────────────────────────────────────────────────────────
    // ASSIGN ROLE  — after registration if role not set
    // ─────────────────────────────────────────────────────────────
    public function assignRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:student,mentor,teacher',
        ]);

        $user = $request->user();

        if ($user->getRoleNames()->isNotEmpty()) {
            return ApiResponse::error('Role already assigned.');
        }

        $user->assignRole($request->role);
        $user->update(['role' => $request->role]);
        $this->createRoleProfile($user, $request->role);

        return ApiResponse::success([
            'role' => $request->role,
        ], 'Role assigned successfully.');
    }

    // ─────────────────────────────────────────────────────────────
    // UPDATE DEVICE TOKEN  — called when FCM token refreshes
    // ─────────────────────────────────────────────────────────────
    public function updateDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token'   => 'required|string',
            'device_id'   => 'nullable|string',
            'device_type' => 'nullable|in:ios,android',
        ]);

        $request->user()->update($request->only(['fcm_token', 'device_id', 'device_type']));

        return ApiResponse::success(null, 'Device token updated.');
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────

    private function userResponse(User $user): array
    {
        $user->loadMissing(['profile', 'mentorProfile', 'teacherProfile', 'studentProfile']);

        $data = [
            'id'                  => $user->id,
            'full_name'           => $user->full_name ?? $user->name,
            'email'               => $user->email,
            'phone'               => $user->phone,
            'role'                => $user->role ?? $user->getRoleNames()->first(),
            'photo_url'           => $user->photo_url,
            'is_verified'         => $user->is_verified,
            'status'              => $user->status,
            'two_factor_enabled'  => $user->two_factor_enabled,
            'timezone'            => $user->timezone,
            'language'            => $user->language,
            'provider'            => $user->provider,
            'last_active_at'      => $user->last_active_at?->toDateTimeString(),
            'created_at'          => $user->created_at?->toDateString(),
        ];

        // Attach role-specific profile
        $role = $user->role ?? $user->getRoleNames()->first();
        $data['profile'] = match ($role) {
            'mentor'  => $user->mentorProfile,
            'teacher' => $user->teacherProfile,
            'student' => $user->studentProfile,
            default   => $user->profile,
        };

        // Subscription status for mentor/teacher
        if (in_array($role, ['mentor', 'teacher'])) {
            $data['has_active_subscription'] = $user->hasActiveSubscription();
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────
    // CHANGE PASSWORD  — while logged in
    // ─────────────────────────────────────────────────────────────
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'new_password'          => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return ApiResponse::error('Current password is incorrect.', 422);
        }

        if (Hash::check($request->new_password, $user->password)) {
            return ApiResponse::error('New password must be different from current password.', 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->delete();

        return ApiResponse::success(null, 'Password changed successfully. Please login again.');
    }

    // ─────────────────────────────────────────────────────────────
    // SWITCH ROLE — STEP 1: Preview
    //
    // Copies common fields to target profile.
    // Does NOT change role yet.
    // Returns: which fields were copied + which are still missing.
    // ─────────────────────────────────────────────────────────────
    public function switchRolePreview(Request $request): JsonResponse
    {
        $request->validate([
            'switch_to' => 'required|in:mentor,teacher',
        ]);

        $user        = $request->user();
        $currentRole = $user->role ?? $user->getRoleNames()->first();
        $switchTo    = $request->switch_to;

        if (!in_array($currentRole, ['mentor', 'teacher'])) {
            return ApiResponse::forbidden('Only mentors and teachers can switch roles.');
        }

        if ($currentRole === $switchTo) {
            return ApiResponse::error("You are already a {$switchTo}.");
        }

        // ── Extract common (overlapping) fields ──────────────────
        $commonFields = $this->extractCommonFields($user, $currentRole);

        // ── Copy to target profile (profile_setup_complete = false) ─
        if ($switchTo === 'mentor') {
            $targetProfile = $user->mentorProfile()->updateOrCreate(
                ['user_id' => $user->id],
                array_merge($commonFields, ['profile_setup_complete' => false])
            );
            $missingFields  = $targetProfile->fresh()->missingFields();
            $requiredFields = \App\Models\MentorProfile::$requiredFields;
        } else {
            $targetProfile = $user->teacherProfile()->updateOrCreate(
                ['user_id' => $user->id],
                array_merge($commonFields, ['profile_setup_complete' => false])
            );
            $missingFields  = $targetProfile->fresh()->missingFields();
            $requiredFields = \App\Models\TeacherProfile::$requiredFields;
        }

        // ── Save pending_role — role has NOT changed yet ──────────
        $user->update(['pending_role' => $switchTo]);

        return ApiResponse::success([
            'current_role'    => $currentRole,
            'switching_to'    => $switchTo,
            'requires_setup'  => count($missingFields) > 0,
            'copied_fields'   => array_keys(array_filter($commonFields, fn($v) => !empty($v))),
            'missing_fields'  => $missingFields,
            'required_fields' => $requiredFields,
            'target_profile'  => $targetProfile->fresh(),
            'next_step'       => count($missingFields) > 0
                ? "Complete the missing fields using PUT /api/v1/switch-role/profile, then call POST /api/v1/switch-role/confirm"
                : "All fields ready. Call POST /api/v1/switch-role/confirm to complete the switch.",
        ], "Preview ready. Please complete the {$switchTo} profile to continue.");
    }

    // ─────────────────────────────────────────────────────────────
    // SWITCH ROLE — STEP 2: Update target profile
    //
    // Accessible while the user still holds their CURRENT role.
    // Fills in missing fields for the PENDING (target) profile.
    // No role middleware needed — pending_role check is the gate.
    // ─────────────────────────────────────────────────────────────
    public function switchRoleUpdateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->pending_role) {
            return ApiResponse::error(
                'No pending role switch found. Call POST /switch-role/preview first.',
                400
            );
        }

        $switchTo = $user->pending_role;

        if ($switchTo === 'teacher') {
            $request->validate([
                'subject'          => 'sometimes|string|max:255',
                'subject_field'    => 'sometimes|string|max:255',
                'short_bio'        => 'sometimes|string|max:1000',
                'degree'           => 'sometimes|string|max:255',
                'designation'      => 'sometimes|string|max:255',
                'experience_years' => 'sometimes|string|max:20',
                'expertise_list'   => 'sometimes|array',
                'expertise_list.*' => 'string|max:100',
                'languages_list'   => 'sometimes|array',
                'languages_list.*' => 'string|max:50',
            ]);

            $updates = array_filter([
                'subject'          => $request->subject,
                'subject_field'    => $request->subject_field,
                'short_bio'        => $request->short_bio,
                'degree'           => $request->degree,
                'designation'      => $request->designation,
                'experience_years' => $request->experience_years,
                'expertise_list'   => $request->expertise_list,
                'languages_list'   => $request->languages_list,
                'languages'        => $request->languages_list
                    ? implode(', ', $request->languages_list)
                    : null,
            ], fn($v) => !is_null($v));

            $profile = $user->teacherProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $updates
            );

            $missing  = $profile->fresh()->missingFields();
            $complete = count($missing) === 0;

            if ($complete) {
                $profile->update(['profile_setup_complete' => true]);
            }

        } else { // mentor

            $request->validate([
                'designation'             => 'sometimes|string|max:255',
                'short_bio'               => 'sometimes|string|max:1000',
                'specialization'          => 'sometimes|string|max:255',
                'industry'                => 'sometimes|string|max:100',
                'price_per_hour'          => 'sometimes|numeric|min:0',
                'experience_years'        => 'sometimes|string|max:20',
                'preferred_student_level' => 'sometimes|in:beginner,intermediate,advanced,all',
                'expertise_list'          => 'sometimes|array',
                'expertise_list.*'        => 'string|max:100',
                'languages_list'          => 'sometimes|array',
                'languages_list.*'        => 'string|max:50',
            ]);

            $updates = array_filter([
                'designation'             => $request->designation,
                'short_bio'               => $request->short_bio,
                'specialization'          => $request->specialization,
                'industry'                => $request->industry,
                'price_per_hour'          => $request->price_per_hour,
                'experience_years'        => $request->experience_years,
                'preferred_student_level' => $request->preferred_student_level,
                'expertise_list'          => $request->expertise_list,
                'languages_list'          => $request->languages_list,
                'languages'               => $request->languages_list
                    ? implode(', ', $request->languages_list)
                    : null,
            ], fn($v) => !is_null($v));

            $profile = $user->mentorProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $updates
            );

            $missing  = $profile->fresh()->missingFields();
            $complete = count($missing) === 0;

            if ($complete) {
                $profile->update(['profile_setup_complete' => true]);
            }
        }

        return ApiResponse::success([
            'pending_role'           => $switchTo,
            'profile_setup_complete' => $complete,
            'missing_fields'         => $missing,
            'profile'                => $profile->fresh(),
            'next_step'              => $complete
                ? 'Profile complete! Call POST /api/v1/switch-role/confirm to finalize the switch.'
                : 'Fill the remaining fields, then call POST /api/v1/switch-role/confirm.',
        ], $complete
            ? 'Target profile complete. Ready to confirm role switch.'
            : 'Profile updated. Some fields are still missing.'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // SWITCH ROLE — STEP 3: Confirm
    //
    // Called after user has filled in missing profile fields.
    // Validates profile is complete, then finalises role change.
    // ─────────────────────────────────────────────────────────────
    public function switchRoleConfirm(Request $request): JsonResponse
    {
        $user      = $request->user();
        $switchTo  = $user->pending_role;

        if (!$switchTo) {
            return ApiResponse::error(
                'No pending role switch found. Call POST /switch-role/preview first.',
                400
            );
        }

        $currentRole = $user->role ?? $user->getRoleNames()->first();

        // ── Validate target profile is complete ──────────────────
        if ($switchTo === 'mentor') {
            $targetProfile = $user->mentorProfile;
            $missing = $targetProfile ? $targetProfile->missingFields() : \App\Models\MentorProfile::$requiredFields;
        } else {
            $targetProfile = $user->teacherProfile;
            $missing = $targetProfile ? $targetProfile->missingFields() : \App\Models\TeacherProfile::$requiredFields;
        }

        if (count($missing) > 0) {
            return ApiResponse::error(
                'Profile is incomplete. Please fill all required fields before switching.',
                422,
                [
                    'missing_fields' => $missing,
                    'hint'           => "Update your profile using PUT /api/v1/switch-role/profile",
                ]
            );
        }

        // ── All good — mark profile complete ─────────────────────
        $targetProfile->update(['profile_setup_complete' => true]);

        // ── Change role (Spatie + users.role column) ─────────────
        $user->removeRole($currentRole);
        $user->assignRole($switchTo);
        $user->update([
            'role'         => $switchTo,
            'pending_role' => null,
        ]);

        // ── Reset subscription for new role ───────────────────────
        $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

        $user->refresh()->load(['mentorProfile', 'teacherProfile', 'profile']);

        return ApiResponse::success([
            'user'           => $this->userResponse($user),
            'switched_from'  => $currentRole,
            'switched_to'    => $switchTo,
            'next_step'      => "Subscribe to a plan at POST /api/v1/{$switchTo}/subscription to unlock all features.",
        ], "Role successfully switched to {$switchTo}. Your profile is complete!");
    }

    // ─────────────────────────────────────────────────────────────
    // SWITCH ROLE — Cancel pending switch
    // ─────────────────────────────────────────────────────────────
    public function switchRoleCancel(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->pending_role) {
            return ApiResponse::error('No pending role switch to cancel.');
        }

        $cancelled = $user->pending_role;
        $user->update(['pending_role' => null]);

        return ApiResponse::success([
            'cancelled_switch_to' => $cancelled,
            'current_role'        => $user->role,
        ], 'Role switch cancelled. You remain as ' . $user->role . '.');
    }

    // ─────────────────────────────────────────────────────────────
    // Private: extract common fields between profiles
    // ─────────────────────────────────────────────────────────────
    private function extractCommonFields(User $user, string $fromRole): array
    {
        $common = [
            'experience_years' => null,
            'short_bio'        => null,
            'intro'            => null,
            'intro_video'      => null,
            'expertise_list'   => null,
            'languages'        => null,
            'languages_list'   => null,
            'designation'      => null,
            'rating'           => 0,
            'total_reviews'    => 0,
            'is_verified'      => false,
        ];

        if ($fromRole === 'mentor' && $user->mentorProfile) {
            $mp = $user->mentorProfile;
            $common['experience_years'] = $mp->experience_years;
            $common['short_bio']        = $mp->short_bio ?? $mp->intro;
            $common['intro']            = $mp->intro;
            $common['intro_video']      = $mp->intro_video;
            $common['expertise_list']   = $mp->expertise_list;
            $common['languages']        = $mp->languages;
            $common['languages_list']   = $mp->languages_list;
            $common['designation']      = $mp->designation;
            $common['rating']           = $mp->rating;
            $common['total_reviews']    = $mp->total_reviews;
            $common['is_verified']      = $mp->is_verified;
        }

        if ($fromRole === 'teacher' && $user->teacherProfile) {
            $tp = $user->teacherProfile;
            $common['experience_years'] = $tp->experience_years;
            $common['short_bio']        = $tp->short_bio ?? $tp->intro;
            $common['intro']            = $tp->intro;
            $common['intro_video']      = $tp->intro_video;
            $common['expertise_list']   = $tp->expertise_list;
            $common['languages']        = $tp->languages;
            $common['languages_list']   = $tp->languages_list;
            $common['designation']      = $tp->designation;
            $common['rating']           = $tp->rating;
            $common['total_reviews']    = $tp->total_reviews;
            $common['is_verified']      = $tp->is_verified;
        }

        // Remove nulls so we don't overwrite existing data
        return array_filter($common, fn($v) => !is_null($v));
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────

    private function createRoleProfile(User $user, string $role): void
    {
        match ($role) {
            'student' => StudentProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['student_id' => 'STU-' . str_pad($user->id, 6, '0', STR_PAD_LEFT)]
            ),
            'mentor'  => $user->mentorProfile()->firstOrCreate(['user_id' => $user->id]),
            'teacher' => $user->teacherProfile()->firstOrCreate(['user_id' => $user->id]),
        };
    }
}
