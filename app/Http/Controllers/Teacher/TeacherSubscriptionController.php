<?php

namespace App\Http\Controllers\Teacher;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherSubscriptionController extends Controller
{
    private array $plans = [
        'monthly'   => [
            'name'     => 'Monthly',
            'price'    => 9.99,
            'days'     => 30,
            'features' => ['Publish unlimited courses', 'Earn from enrollments', 'Basic analytics'],
        ],
        'quarterly' => [
            'name'     => 'Quarterly',
            'price'    => 24.99,
            'days'     => 90,
            'features' => ['All Monthly features', 'Featured teacher listing', 'Priority support'],
        ],
        'annual'    => [
            'name'     => 'Annual',
            'price'    => 79.99,
            'days'     => 365,
            'features' => ['All Quarterly features', 'Top teacher badge', 'Reduced platform commission'],
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $active = $user->activeSubscription()->first();

        return ApiResponse::success([
            'has_active_subscription' => $user->hasActiveSubscription(),
            'active_plan'             => $active,
            'available_plans'         => $this->plans,
            'note'                    => 'A subscription is required to publish courses. You can always create draft courses for free.',
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:monthly,quarterly,annual',
        ]);

        $user = $request->user();
        $plan = $this->plans[$request->plan];

        $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

        $subscription = Subscription::create([
            'user_id'    => $user->id,
            'plan'       => $request->plan,
            'price'      => $plan['price'],
            'starts_at'  => now(),
            'expires_at' => now()->addDays($plan['days']),
            'status'     => 'active',
        ]);

        $user->teacherProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['is_verified' => true]
        );

        return ApiResponse::created([
            'subscription'            => $subscription,
            'has_active_subscription' => true,
            'can_publish_courses'     => true,
        ], "Subscribed to {$plan['name']} plan. You can now publish courses.");
    }

    public function cancel(Request $request): JsonResponse
    {
        $active = $request->user()->activeSubscription()->first();

        if (!$active) {
            return ApiResponse::error('No active subscription found.');
        }

        $active->update(['status' => 'cancelled']);

        return ApiResponse::success([
            'has_active_subscription' => false,
            'can_publish_courses'     => false,
        ], 'Subscription cancelled. Existing published courses remain active until subscription expires.');
    }
}
