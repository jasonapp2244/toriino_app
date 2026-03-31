<?php

namespace App\Http\Controllers\Common;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Earning;
use App\Models\MentorSession;
use App\Models\Payment;
use App\Models\SessionBooking;
use App\Models\Subscription;
use App\Models\AppNotification;
use App\Services\EarningService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    private StripeService $stripe;
    private EarningService $earningService;

    // Subscription plans
    private array $plans = [
        'monthly'   => ['price' => 9.99,  'days' => 30],
        'quarterly' => ['price' => 24.99, 'days' => 90],
        'annual'    => ['price' => 79.99, 'days' => 365],
    ];

    public function __construct(StripeService $stripe, EarningService $earningService)
    {
        $this->stripe = $stripe;
        $this->earningService = $earningService;
    }

    // ═══════════════════════════════════════════════════════════
    // COURSE PURCHASE — PaymentIntent flow
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /api/v1/payments/course/intent
     * Creates a Stripe PaymentIntent for course purchase.
     * Returns client_secret for Flutter to confirm payment.
     */
    public function createCoursePaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $course  = Course::findOrFail($request->course_id);
        $student = $request->user();

        if ($course->isEnrolledBy($student->id)) {
            return ApiResponse::error('Already enrolled in this course.');
        }

        // Free courses — enroll immediately
        if ($course->price <= 0) {
            return $this->enrollStudentInCourse($student, $course);
        }

        $payment = Payment::create([
            'user_id'           => $student->id,
            'payable_type'      => 'course',
            'payable_id'        => $course->id,
            'amount'            => $course->price,
            'currency'          => 'USD',
            'status'            => 'pending',
            'payment_method'    => 'stripe',
            'payment_reference' => Payment::generateReference(),
        ]);

        $intent = $this->stripe->createPaymentIntent($course->price, 'usd', [
            'payment_id' => $payment->id,
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'type'       => 'course',
        ]);

        $payment->update(['transaction_id' => $intent->id]);

        return ApiResponse::success([
            'client_secret'     => $intent->client_secret,
            'payment_intent_id' => $intent->id,
            'payment_id'        => $payment->id,
            'amount'            => $course->price,
            'currency'          => 'USD',
        ]);
    }

    /**
     * POST /api/v1/payments/course/confirm
     * After Flutter confirms payment, verify and enroll student.
     */
    public function confirmCoursePayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
            'course_id'         => 'required|exists:courses,id',
        ]);

        return DB::transaction(function () use ($request) {
            $payment = Payment::where('transaction_id', $request->payment_intent_id)->lockForUpdate()->first();
            if (!$payment) {
                return ApiResponse::error('Payment not found.', 404);
            }

            if ($payment->isPaid()) {
                return ApiResponse::error('Payment already processed.');
            }

            // Verify with Stripe
            $intent = $this->stripe->retrievePaymentIntent($request->payment_intent_id);
            if ($intent->status !== 'succeeded') {
                $payment->update(['status' => 'failed']);
                return ApiResponse::error('Payment not completed. Status: ' . $intent->status, 402);
            }

            $payment->update([
                'status'           => 'paid',
                'paid_at'          => now(),
                'gateway_response' => method_exists($intent, 'toArray') ? $intent->toArray() : (array) $intent,
            ]);

            $course  = Course::findOrFail($request->course_id);
            $student = $request->user();

            return $this->enrollStudentInCourse($student, $course, $payment);
        });
    }

    // ═══════════════════════════════════════════════════════════
    // SESSION BOOKING — PaymentIntent flow
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /api/v1/payments/session/intent
     * Creates a Stripe PaymentIntent for session booking.
     */
    public function createSessionPaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|exists:mentor_sessions,id',
        ]);

        $session = MentorSession::findOrFail($request->session_id);
        $student = $request->user();

        if ($session->status !== 'upcoming') {
            return ApiResponse::error('Session is not available for booking.');
        }

        if ($session->seatsAvailable() <= 0) {
            return ApiResponse::error('Session is fully booked.');
        }

        $existing = SessionBooking::where('session_id', $session->id)
            ->where('student_id', $student->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->first();
        if ($existing) {
            return ApiResponse::error('You have already booked this session.');
        }

        // Free sessions — book immediately
        if ($session->price <= 0) {
            return $this->bookStudentInSession($student, $session);
        }

        $payment = Payment::create([
            'user_id'           => $student->id,
            'payable_type'      => 'session',
            'payable_id'        => $session->id,
            'amount'            => $session->price,
            'currency'          => 'USD',
            'status'            => 'pending',
            'payment_method'    => 'stripe',
            'payment_reference' => Payment::generateReference(),
        ]);

        $intent = $this->stripe->createPaymentIntent($session->price, 'usd', [
            'payment_id' => $payment->id,
            'user_id'    => $student->id,
            'session_id' => $session->id,
            'type'       => 'session',
        ]);

        $payment->update(['transaction_id' => $intent->id]);

        return ApiResponse::success([
            'client_secret'     => $intent->client_secret,
            'payment_intent_id' => $intent->id,
            'payment_id'        => $payment->id,
            'amount'            => $session->price,
            'currency'          => 'USD',
        ]);
    }

    /**
     * POST /api/v1/payments/session/confirm
     * After Flutter confirms payment, verify and book student.
     */
    public function confirmSessionPayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
            'session_id'        => 'required|exists:mentor_sessions,id',
        ]);

        return DB::transaction(function () use ($request) {
            $payment = Payment::where('transaction_id', $request->payment_intent_id)->lockForUpdate()->first();
            if (!$payment) {
                return ApiResponse::error('Payment not found.', 404);
            }

            if ($payment->isPaid()) {
                return ApiResponse::error('Payment already processed.');
            }

            $intent = $this->stripe->retrievePaymentIntent($request->payment_intent_id);
            if ($intent->status !== 'succeeded') {
                $payment->update(['status' => 'failed']);
                return ApiResponse::error('Payment not completed. Status: ' . $intent->status, 402);
            }

            $payment->update([
                'status'           => 'paid',
                'paid_at'          => now(),
                'gateway_response' => method_exists($intent, 'toArray') ? $intent->toArray() : (array) $intent,
            ]);

            $session = MentorSession::findOrFail($request->session_id);
            $student = $request->user();

            return $this->bookStudentInSession($student, $session, $payment);
        });
    }

    // ═══════════════════════════════════════════════════════════
    // SUBSCRIPTION — Checkout flow
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /api/v1/payments/subscription/intent
     * Creates a PaymentIntent for subscription purchase.
     */
    public function createSubscriptionPaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:monthly,quarterly,annual',
            'role' => 'required|in:mentor,teacher',
        ]);

        $plan  = $this->plans[$request->plan];
        $user  = $request->user();

        $payment = Payment::create([
            'user_id'           => $user->id,
            'payable_type'      => 'subscription',
            'payable_id'        => 0,
            'amount'            => $plan['price'],
            'currency'          => 'USD',
            'status'            => 'pending',
            'payment_method'    => 'stripe',
            'payment_reference' => Payment::generateReference(),
        ]);

        $intent = $this->stripe->createPaymentIntent($plan['price'], 'usd', [
            'payment_id' => $payment->id,
            'user_id'    => $user->id,
            'plan'       => $request->plan,
            'role'       => $request->role,
            'type'       => 'subscription',
        ]);

        $payment->update(['transaction_id' => $intent->id]);

        return ApiResponse::success([
            'client_secret'     => $intent->client_secret,
            'payment_intent_id' => $intent->id,
            'payment_id'        => $payment->id,
            'amount'            => $plan['price'],
            'currency'          => 'USD',
        ]);
    }

    /**
     * POST /api/v1/payments/subscription/confirm
     * After Flutter confirms payment, activate subscription.
     */
    public function confirmSubscriptionPayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
            'plan'              => 'required|in:monthly,quarterly,annual',
            'role'              => 'required|in:mentor,teacher',
        ]);

        return DB::transaction(function () use ($request) {
            $payment = Payment::where('transaction_id', $request->payment_intent_id)->lockForUpdate()->first();
            if (!$payment) {
                return ApiResponse::error('Payment not found.', 404);
            }

            if ($payment->isPaid()) {
                return ApiResponse::error('Payment already processed.');
            }

            $intent = $this->stripe->retrievePaymentIntent($request->payment_intent_id);
            if ($intent->status !== 'succeeded') {
                $payment->update(['status' => 'failed']);
                return ApiResponse::error('Payment not completed.', 402);
            }

            $payment->update([
                'status'           => 'paid',
                'paid_at'          => now(),
                'gateway_response' => method_exists($intent, 'toArray') ? $intent->toArray() : (array) $intent,
            ]);

            $user = $request->user();
            $plan = $this->plans[$request->plan];

            // Cancel existing subscription
            $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

            // Create new subscription
            $subscription = Subscription::create([
                'user_id'    => $user->id,
                'plan'       => $request->plan,
                'price'      => $plan['price'],
                'starts_at'  => now(),
                'expires_at' => now()->addDays($plan['days']),
                'status'     => 'active',
            ]);

            // Feature profile
            if ($request->role === 'mentor') {
                $user->mentorProfile()?->update(['is_featured' => true]);
            } elseif ($request->role === 'teacher') {
                $user->teacherProfile()?->update(['is_featured' => true]);
            }

            return ApiResponse::success([
                'subscription'            => $subscription,
                'has_active_subscription' => true,
            ], 'Subscription activated!');
        });
    }

    // ═══════════════════════════════════════════════════════════
    // PAYMENT HISTORY
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /api/v1/payments
     * List user's payment history.
     */
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return ApiResponse::success($payments);
    }

    /**
     * GET /api/v1/payments/{id}
     * Get payment detail.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $payment = Payment::where('user_id', $request->user()->id)->find($id);
        if (!$payment) {
            return ApiResponse::notFound('Payment not found.');
        }
        return ApiResponse::success($payment);
    }

    // ═══════════════════════════════════════════════════════════
    // STRIPE WEBHOOK
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /api/v1/webhooks/stripe
     * Handle Stripe webhook events.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = $this->stripe->constructWebhookEvent($payload, $sigHeader);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Webhook verification failed'], 400);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSucceeded($event->data->object);
                break;
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($event->data->object);
                break;
        }

        return response()->json(['status' => 'ok']);
    }

    // ═══════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════

    private function enrollStudentInCourse($student, Course $course, ?Payment $payment = null): JsonResponse
    {
        $enrollment = CourseEnrollment::create([
            'course_id'   => $course->id,
            'student_id'  => $student->id,
            'amount_paid' => $course->price,
        ]);

        $course->increment('total_enrollments');

        // Create earning for teacher (minus commission)
        if ($course->teacher_id && $course->price > 0) {
            $this->earningService->createForCourse($course->teacher_id, $course);
        }

        if ($course->teacher_id) {
            AppNotification::create([
                'user_id' => $course->teacher_id,
                'title'   => 'New Course Enrollment',
                'body'    => $student->name . ' enrolled in "' . $course->title . '".',
                'type'    => 'course_enrolled',
            ]);
        }

        return ApiResponse::created($enrollment->load('course'), 'Enrolled successfully');
    }

    private function bookStudentInSession($student, MentorSession $session, ?Payment $payment = null): JsonResponse
    {
        $booking = SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $student->id,
            'status'     => 'confirmed',
        ]);

        $session->increment('seats_booked');

        // Create earning for mentor (minus commission)
        if ($session->price > 0) {
            $this->earningService->createForSession($session->mentor_id, $session);
        }

        AppNotification::create([
            'user_id' => $session->mentor_id,
            'title'   => 'New Session Booking',
            'body'    => $student->name . ' booked "' . $session->title . '".',
            'type'    => 'session_booked',
        ]);

        return ApiResponse::created($booking->load('session'), 'Session booked successfully');
    }

    private function handlePaymentSucceeded($paymentIntent): void
    {
        $payment = Payment::where('transaction_id', $paymentIntent->id)->first();
        if ($payment && !$payment->isPaid()) {
            $payment->update([
                'status'           => 'paid',
                'paid_at'          => now(),
                'gateway_response' => method_exists($paymentIntent, 'toArray') ? $paymentIntent->toArray() : (array) $paymentIntent,
            ]);
        }
    }

    private function handlePaymentFailed($paymentIntent): void
    {
        $payment = Payment::where('transaction_id', $paymentIntent->id)->first();
        if ($payment) {
            $payment->update([
                'status'           => 'failed',
                'gateway_response' => method_exists($paymentIntent, 'toArray') ? $paymentIntent->toArray() : (array) $paymentIntent,
            ]);
        }
    }
}
