<?php

use Illuminate\Support\Facades\Route;

// Auth
use App\Http\Controllers\Auth\AuthController;

// Mentor
use App\Http\Controllers\Mentor\MentorHomeController;
use App\Http\Controllers\Mentor\MentorSessionController;
use App\Http\Controllers\Mentor\MentorEarningController;
use App\Http\Controllers\Mentor\MentorProfileController;
use App\Http\Controllers\Mentor\MentorAvailabilityController;
use App\Http\Controllers\Mentor\MentorSubscriptionController;

// Student
use App\Http\Controllers\Student\StudentHomeController;
use App\Http\Controllers\Student\StudentSessionController;
use App\Http\Controllers\Student\StudentCourseController;
use App\Http\Controllers\Student\StudentProfileController;

// Teacher
use App\Http\Controllers\Teacher\TeacherHomeController;
use App\Http\Controllers\Teacher\TeacherCourseController;
use App\Http\Controllers\Teacher\TeacherProfileController;
use App\Http\Controllers\Teacher\TeacherEarningController;
use App\Http\Controllers\Teacher\TeacherSubscriptionController;

// Common
use App\Http\Controllers\Common\NotificationController;
use App\Http\Controllers\Common\AiChatController;
use App\Http\Controllers\Common\ReviewController;
use App\Http\Controllers\Common\SupportTicketController;
use App\Http\Controllers\Common\SearchController;

Route::prefix('v1')->group(function () {

    // ═══════════════════════════════════════════════════════════
    // PUBLIC — no auth required
    // ═══════════════════════════════════════════════════════════

    // Auth
    Route::post('register',        [AuthController::class, 'register']);
    Route::post('otp-verify',      [AuthController::class, 'otpVerify']);
    Route::post('resend-otp',      [AuthController::class, 'resendOtp']);
    Route::post('login',           [AuthController::class, 'login']);
    Route::post('social-login',    [AuthController::class, 'socialLogin']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password',  [AuthController::class, 'resetPassword']);

    // Discovery & Search
    Route::get('search',           [SearchController::class, 'search']);
    Route::get('mentors',          [SearchController::class, 'mentors']);
    Route::get('privacy-policy',   [SearchController::class, 'privacyPolicy']);

    // Public Profiles
    Route::get('mentors/{id}',              [MentorProfileController::class,      'publicProfile']);
    Route::get('mentors/{id}/availability', [MentorAvailabilityController::class, 'publicAvailability']);
    Route::get('teachers/{id}',             [TeacherProfileController::class,     'publicProfile']);
    Route::get('students/{id}',             [StudentProfileController::class,     'publicProfile']);

    // Public Courses & Reviews
    Route::get('courses',             [StudentCourseController::class, 'index']);
    Route::get('courses/{id}',        [StudentCourseController::class, 'show']);
    Route::get('reviews/user/{id}',   [ReviewController::class,        'indexForUser']);
    Route::get('reviews/course/{id}', [ReviewController::class,        'indexForCourse']);

    // ═══════════════════════════════════════════════════════════
    // AUTHENTICATED — requires valid Sanctum token
    // ═══════════════════════════════════════════════════════════
    Route::middleware('auth:sanctum')->group(function () {

        // Auth (authenticated)
        Route::post('logout',             [AuthController::class, 'logout']);
        Route::get('me',                  [AuthController::class, 'me']);
        Route::post('assign-role',        [AuthController::class, 'assignRole']);
        Route::post('change-password',    [AuthController::class, 'changePassword']);
        Route::post('update-device-token',[AuthController::class, 'updateDeviceToken']);

        // Switch Role — 3-step flow
        Route::post('switch-role/preview', [AuthController::class, 'switchRolePreview']);  // STEP 1
        // STEP 2 → PUT /mentor/profile  or  PUT /teacher/profile  (fill missing fields)
        Route::post('switch-role/confirm', [AuthController::class, 'switchRoleConfirm']);  // STEP 3
        Route::post('switch-role/cancel',  [AuthController::class, 'switchRoleCancel']);   // Cancel

        // ───────────────────────────────────────────────────────
        // MENTOR  (role: mentor)
        // Business Rule: subscription required to create sessions
        // ───────────────────────────────────────────────────────
        Route::middleware('role:mentor')->prefix('mentor')->group(function () {

            Route::get('dashboard', [MentorHomeController::class, 'dashboard']);

            // Subscription — check/subscribe before creating sessions
            Route::get('subscription',    [MentorSubscriptionController::class, 'index']);
            Route::post('subscription',   [MentorSubscriptionController::class, 'subscribe']);
            Route::delete('subscription', [MentorSubscriptionController::class, 'cancel']);

            // Sessions — requires active subscription (checked inside controller)
            Route::get('sessions',                [MentorSessionController::class, 'index']);
            Route::post('sessions',               [MentorSessionController::class, 'store']);          // ← subscription gate
            Route::get('sessions/{id}',           [MentorSessionController::class, 'show']);
            Route::put('sessions/{id}',           [MentorSessionController::class, 'update']);         // NEW
            Route::post('sessions/{id}/start',    [MentorSessionController::class, 'startSession']);
            Route::post('sessions/{id}/complete', [MentorSessionController::class, 'completeSession']);
            Route::delete('sessions/{id}',        [MentorSessionController::class, 'destroy']);

            // Availability
            Route::get('availability',         [MentorAvailabilityController::class, 'index']);
            Route::post('availability',        [MentorAvailabilityController::class, 'store']);
            Route::put('availability/{id}',    [MentorAvailabilityController::class, 'update']);
            Route::delete('availability/{id}', [MentorAvailabilityController::class, 'destroy']);

            // Earnings & Withdrawals
            Route::get('earnings',           [MentorEarningController::class, 'index']);
            Route::post('earnings/withdraw', [MentorEarningController::class, 'withdraw']);

            // Profile
            Route::get('profile',              [MentorProfileController::class, 'show']);
            Route::put('profile',              [MentorProfileController::class, 'update']);
            Route::post('profile/photo',       [MentorProfileController::class, 'uploadPhoto']);       // NEW
            Route::post('profile/intro-video', [MentorProfileController::class, 'uploadIntroVideo']);
        });

        // ───────────────────────────────────────────────────────
        // STUDENT  (role: student)
        // Business Rule: session booking / course enrollment flows
        // ───────────────────────────────────────────────────────
        Route::middleware('role:student')->prefix('student')->group(function () {

            Route::get('dashboard', [StudentHomeController::class, 'dashboard']);

            // Session bookings
            Route::get('sessions',         [StudentSessionController::class, 'index']);
            Route::post('sessions/book',   [StudentSessionController::class, 'book']);
            Route::delete('sessions/{id}', [StudentSessionController::class, 'cancel']);

            // Course enrollment & progress
            Route::post('courses/enroll',                                  [StudentCourseController::class, 'enroll']);
            Route::get('courses/my',                                       [StudentCourseController::class, 'myCourses']);
            Route::get('courses/completed',                                [StudentCourseController::class, 'completedCourses']);
            Route::get('courses/{courseId}/progress',                      [StudentCourseController::class, 'courseProgress']);          // NEW
            Route::post('courses/{courseId}/lessons/{lessonId}/complete',  [StudentCourseController::class, 'completeLesson']);           // NEW

            // Profile
            Route::get('profile',                  [StudentProfileController::class, 'show']);
            Route::put('profile',                  [StudentProfileController::class, 'update']);
            Route::post('profile/photo',           [StudentProfileController::class, 'uploadPhoto']);
            Route::post('profile/change-password', [StudentProfileController::class, 'changePassword']);
        });

        // ───────────────────────────────────────────────────────
        // TEACHER  (role: teacher)
        // Business Rule: subscription required to PUBLISH courses
        //               (creating draft is free)
        // ───────────────────────────────────────────────────────
        Route::middleware('role:teacher')->prefix('teacher')->group(function () {

            Route::get('dashboard', [TeacherHomeController::class, 'dashboard']);

            // Subscription — check/subscribe before publishing
            Route::get('subscription',    [TeacherSubscriptionController::class, 'index']);
            Route::post('subscription',   [TeacherSubscriptionController::class, 'subscribe']);
            Route::delete('subscription', [TeacherSubscriptionController::class, 'cancel']);

            // Courses — create/edit free, publish requires subscription
            Route::get('courses',                   [TeacherCourseController::class, 'index']);
            Route::post('courses',                  [TeacherCourseController::class, 'store']);           // free (draft)
            Route::get('courses/{id}',              [TeacherCourseController::class, 'show']);
            Route::put('courses/{id}',              [TeacherCourseController::class, 'update']);
            Route::post('courses/{id}/publish',     [TeacherCourseController::class, 'publish']);         // ← subscription gate
            Route::post('courses/{id}/thumbnail',   [TeacherCourseController::class, 'uploadThumbnail']); // NEW
            Route::delete('courses/{id}',           [TeacherCourseController::class, 'destroy']);

            // Lessons
            Route::post('courses/{courseId}/lessons',  [TeacherCourseController::class, 'storeLesson']);   // fixed typo
            Route::post('lessons/{lessonId}/video',    [TeacherCourseController::class, 'uploadLessonVideo']); // NEW
            Route::put('lessons/{lessonId}',           [TeacherCourseController::class, 'updateLesson']);
            Route::delete('lessons/{lessonId}',        [TeacherCourseController::class, 'deleteLesson']);

            // Earnings & Withdrawals
            Route::get('earnings',           [TeacherEarningController::class, 'index']);
            Route::post('earnings/withdraw', [TeacherEarningController::class, 'withdraw']);

            // Profile
            Route::get('profile',                  [TeacherProfileController::class, 'show']);
            Route::put('profile',                  [TeacherProfileController::class, 'update']);
            Route::post('profile/photo',           [TeacherProfileController::class, 'uploadPhoto']);      // NEW
            Route::post('profile/intro-video',     [TeacherProfileController::class, 'uploadIntroVideo']);
            Route::post('profile/change-password', [TeacherProfileController::class, 'changePassword']);
        });

        // ───────────────────────────────────────────────────────
        // COMMON — all authenticated roles
        // ───────────────────────────────────────────────────────

        // Notifications
        Route::get('notifications',             [NotificationController::class, 'index']);
        Route::post('notifications/read-all',   [NotificationController::class, 'markAllAsRead']);
        Route::post('notifications/{id}/read',  [NotificationController::class, 'markAsRead']);
        Route::delete('notifications/{id}',     [NotificationController::class, 'destroy']);

        // AI Tutor Chat
        Route::get('ai-chat',    [AiChatController::class, 'history']);
        Route::post('ai-chat',   [AiChatController::class, 'ask']);
        Route::delete('ai-chat', [AiChatController::class, 'clearHistory']);

        // Reviews
        Route::post('reviews', [ReviewController::class, 'store']);

        // Support Tickets
        Route::get('support',             [SupportTicketController::class, 'index']);
        Route::post('support',            [SupportTicketController::class, 'store']);
        Route::get('support/{id}',        [SupportTicketController::class, 'show']);
        Route::post('support/{id}/close', [SupportTicketController::class, 'close']);  // NEW
    });
});
