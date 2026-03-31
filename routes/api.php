<?php

use Illuminate\Support\Facades\Artisan;
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
use App\Http\Controllers\Common\CourseDropdownController;
use App\Http\Controllers\Common\MuxWebhookController;
use App\Http\Controllers\Common\ChatController;

Route::prefix('v1')->group(function () {

    // ═══════════════════════════════════════════════════════════
    // PUBLIC — no auth required
    // ═══════════════════════════════════════════════════════════

    //api test
    Route::get('api-test', function () {
        return response()->json([
            'message' => 'API is working',
        ]);
    });

    //cache clear — admin only
    Route::middleware('auth:sanctum')->get('cache-clear', function () {
        Artisan::call('cache:clear');
        return response()->json([
            'message' => 'Cache cleared',
        ]);
    });

    // Auth (rate-limited to prevent brute force)
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('register',        [AuthController::class, 'register']);
        Route::post('otp-verify',      [AuthController::class, 'otpVerify']);
        Route::post('resend-otp',      [AuthController::class, 'resendOtp']);
        Route::post('login',           [AuthController::class, 'login']);
        Route::post('social-login',    [AuthController::class, 'socialLogin']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password',  [AuthController::class, 'resetPassword']);
    });

    // Discovery & Search
    Route::get('search',           [SearchController::class, 'search']);
    Route::get('mentors',          [SearchController::class, 'mentors']);
    Route::get('teachers',         [SearchController::class, 'teachers']);
    Route::get('privacy-policy',   [SearchController::class, 'privacyPolicy']);

    // Public Profiles
    Route::get('mentors/{id}',              [MentorProfileController::class,      'publicProfile']);
    Route::get('mentors/{id}/availability', [MentorAvailabilityController::class, 'publicAvailability']);
    Route::get('teachers/{id}',             [TeacherProfileController::class,     'publicProfile']);
    Route::get('students/{id}',             [StudentProfileController::class,     'publicProfile']);

    // Course Dropdowns
    Route::get('course-categories',   [CourseDropdownController::class, 'categories']);
    Route::get('course-levels',       [CourseDropdownController::class, 'levels']);

    // Mentor Profile Dropdowns
    Route::get('industries',          [CourseDropdownController::class, 'industries']);
    Route::get('languages',           [CourseDropdownController::class, 'languages']);
    Route::get('mentor-student-levels',[CourseDropdownController::class, 'mentorStudentLevels']);

    // Mux webhook (called by Mux servers, not by app clients)
    Route::post('webhooks/mux', [MuxWebhookController::class, 'handle']);

    // Stripe webhook (called by Stripe, no auth required)
    Route::post('webhooks/stripe', [\App\Http\Controllers\Common\PaymentController::class, 'handleWebhook']);

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

        // Switch Role — 3-step flow (all role-agnostic: only auth:sanctum required)
        Route::post('switch-role/preview', [AuthController::class, 'switchRolePreview']);       // STEP 1
        Route::put('switch-role/profile',  [AuthController::class, 'switchRoleUpdateProfile']); // STEP 2 ← NEW
        Route::post('switch-role/confirm', [AuthController::class, 'switchRoleConfirm']);       // STEP 3
        Route::post('switch-role/cancel',  [AuthController::class, 'switchRoleCancel']);        // Cancel

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

            // Sessions — requires active subscription (?period=weekly|monthly|yearly|all)
            // Chat bubble on session card → POST /chat/conversations with that student's ID
            Route::get('sessions',                       [MentorSessionController::class, 'index']);
            Route::get('sessions/calendar',              [MentorSessionController::class, 'calendarSessions']);
            Route::post('sessions',                      [MentorSessionController::class, 'store']);
            Route::get('sessions/{id}',                  [MentorSessionController::class, 'show']);
            Route::put('sessions/{id}',                  [MentorSessionController::class, 'update']);
            Route::post('sessions/{id}/start',           [MentorSessionController::class, 'startSession']);
            Route::post('sessions/{id}/complete',        [MentorSessionController::class, 'completeSession']);
            Route::delete('sessions/{id}',               [MentorSessionController::class, 'destroy']);
            // Group session join key
            Route::get('sessions/{id}/join-key',         [MentorSessionController::class, 'getJoinKey']);
            Route::post('sessions/{id}/regenerate-key',  [MentorSessionController::class, 'regenerateJoinKey']);
            // Video call meeting info (Jitsi)
            Route::get('sessions/{id}/meeting-info',     [MentorSessionController::class, 'getMeetingInfo']);
            // Heartbeat: mentor app pings every 30s while in call
            Route::post('sessions/{id}/heartbeat',       [MentorSessionController::class, 'heartbeat']);

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

            // Session bookings  (?status=booked|completed|cancelled|all  &period=weekly|monthly|yearly)
            // Chat bubble on session card → POST /chat/conversations with the other user's ID
            Route::get('sessions',                         [StudentSessionController::class, 'index']);
            Route::post('sessions/book',                   [StudentSessionController::class, 'book']);
            Route::post('sessions/join-by-key',            [StudentSessionController::class, 'joinByKey']);
            Route::get('sessions/available/{mentorId}',    [StudentSessionController::class, 'availableByMentor']);
            Route::get('sessions/{id}',                    [StudentSessionController::class, 'show']);
            Route::post('sessions/{id}/review',            [StudentSessionController::class, 'submitReview']);
            Route::post('sessions/{id}/reschedule',        [StudentSessionController::class, 'reschedule']);
            Route::delete('sessions/{id}',                 [StudentSessionController::class, 'cancel']);
            // Video call meeting info (Jitsi) — id is booking_id
            Route::get('sessions/{id}/meeting-info',       [StudentSessionController::class, 'getMeetingInfo']);
            // Poll session status (for reconnect check) — id is booking_id
            Route::get('sessions/{id}/status',             [StudentSessionController::class, 'sessionStatus']);

            // Course enrollment & progress
            Route::post('courses/enroll',                                         [StudentCourseController::class, 'enroll']);
            Route::get('courses/my',                                              [StudentCourseController::class, 'myCourses']);
            Route::get('courses/completed',                                       [StudentCourseController::class, 'completedCourses']);

            // In-Progress Courses screen (Ongoing / Completed / Favorites tabs)
            // GET /api/v1/student/courses/in-progress?tab=ongoing|completed|favorites
            Route::get('courses/in-progress',                                     [StudentCourseController::class, 'inProgressCourses']);

            // Favorites — dedicated flat list
            // GET /api/v1/student/courses/favorites
            Route::get('courses/favorites',                                       [StudentCourseController::class, 'listFavorites']);
            Route::post('courses/{courseId}/favorite',                            [StudentCourseController::class, 'toggleFavorite']);

            Route::get('courses/{courseId}/progress',                             [StudentCourseController::class, 'courseProgress']);
            Route::get('courses/{courseId}/lessons/{lessonId}',                   [StudentCourseController::class, 'lessonDetail']);
            Route::post('courses/{courseId}/lessons/{lessonId}/video-progress',   [StudentCourseController::class, 'updateVideoProgress']);
            Route::post('courses/{courseId}/lessons/{lessonId}/complete',         [StudentCourseController::class, 'completeLesson']);

            // Certificate
            Route::get('courses/{courseId}/certificate',          [StudentCourseController::class, 'getCertificate']);
            Route::get('courses/{courseId}/certificate/download', [StudentCourseController::class, 'downloadCertificate']);

            // Profile
            Route::get('profile',                  [StudentProfileController::class, 'show']);
            Route::put('profile',                  [StudentProfileController::class, 'update']);
            Route::post('profile/photo',           [StudentProfileController::class, 'uploadPhoto']);
            Route::post('profile/change-password', [StudentProfileController::class, 'changePassword']);
            Route::get('profile/certificates',     [StudentProfileController::class, 'myCertificates']);
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
            Route::post('courses/{courseId}/lessons',                        [TeacherCourseController::class, 'storeLesson']);
            Route::put('lessons/{lessonId}',                                 [TeacherCourseController::class, 'updateLesson']);
            Route::delete('lessons/{lessonId}',                              [TeacherCourseController::class, 'deleteLesson']);

            // Lesson Video — Mux direct upload (preferred) or server upload (fallback)
            Route::post('lessons/{lessonId}/mux-upload',                     [TeacherCourseController::class, 'initMuxUpload']);
            Route::post('lessons/{lessonId}/video',                          [TeacherCourseController::class, 'uploadLessonVideo']);

            // Lesson Attachments (PDF / PPT / image)
            Route::post('lessons/{lessonId}/attachments',                    [TeacherCourseController::class, 'addLessonAttachment']);
            Route::delete('lessons/{lessonId}/attachments/{attachmentId}',   [TeacherCourseController::class, 'deleteLessonAttachment']);

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
        Route::post('ai-chat',   [AiChatController::class, 'ask'])->middleware('throttle:30,1');
        Route::delete('ai-chat', [AiChatController::class, 'clearHistory']);

        // Reviews
        Route::post('reviews', [ReviewController::class, 'store']);

        // Support Tickets
        Route::get('support',             [SupportTicketController::class, 'index']);
        Route::post('support',            [SupportTicketController::class, 'store']);
        Route::get('support/{id}',        [SupportTicketController::class, 'show']);
        Route::post('support/{id}/close', [SupportTicketController::class, 'close']);

        // ───────────────────────────────────────────────────────
        // ONE-TO-ONE CHAT  (all roles: mentor ↔ student, teacher ↔ student,
        //                   student ↔ student, mentor ↔ teacher, etc.)
        // Real-time: Reverb WebSocket  →  private-conversation.{id}
        // ───────────────────────────────────────────────────────
        Route::prefix('chat')->group(function () {
            // Conversations
            Route::get('conversations',              [ChatController::class, 'index']);   // list my conversations
            Route::post('conversations',             [ChatController::class, 'start']);   // open or get conversation
            Route::get('conversations/{id}',         [ChatController::class, 'show']);    // conversation detail
            Route::delete('conversations/{id}',      [ChatController::class, 'destroy']); // delete conversation

            // Messages
            Route::get('conversations/{id}/messages',  [ChatController::class, 'messages']);  // paginated history
            Route::post('conversations/{id}/messages', [ChatController::class, 'send'])->middleware('throttle:100,1'); // send message (rate-limited)
            Route::post('conversations/{id}/read',     [ChatController::class, 'markRead']);  // mark read

            // User search (to start a new chat)
            Route::get('users/search', [ChatController::class, 'searchUsers']); // ?q=name&role=mentor
        });

        // ───────────────────────────────────────────────────────
        // PAYMENTS (Stripe)
        // ───────────────────────────────────────────────────────
        Route::prefix('payments')->group(function () {
            // Course purchase
            Route::post('course/intent',  [\App\Http\Controllers\Common\PaymentController::class, 'createCoursePaymentIntent']);
            Route::post('course/confirm', [\App\Http\Controllers\Common\PaymentController::class, 'confirmCoursePayment']);

            // Session booking
            Route::post('session/intent',  [\App\Http\Controllers\Common\PaymentController::class, 'createSessionPaymentIntent']);
            Route::post('session/confirm', [\App\Http\Controllers\Common\PaymentController::class, 'confirmSessionPayment']);

            // Subscription
            Route::post('subscription/intent',  [\App\Http\Controllers\Common\PaymentController::class, 'createSubscriptionPaymentIntent']);
            Route::post('subscription/confirm', [\App\Http\Controllers\Common\PaymentController::class, 'confirmSubscriptionPayment']);

            // Payment history
            Route::get('/',    [\App\Http\Controllers\Common\PaymentController::class, 'index']);
            Route::get('{id}', [\App\Http\Controllers\Common\PaymentController::class, 'show']);
        });

        // Reverb / Laravel Echo — WebSocket channel authentication
        // Mobile client POSTs here with Bearer token + socket_id to get a signed channel token
        Route::post('broadcasting/auth', \App\Http\Controllers\Common\BroadcastAuthController::class);
    });
});
