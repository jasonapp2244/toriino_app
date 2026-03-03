<?php

namespace App\Http\Controllers\Mentor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorSubscriptionController extends Controller
{
    // Plans available for mentors
    private array $plans = [
        'monthly'   => [
            'name'     => 'Monthly',
            'price'    => 9.99,
            'days'     => 30,
            'features' => ['Create unlimited sessions', 'Featured profile listing', 'Priority support'],
        ],
        'quarterly' => [
            'name'     => 'Quarterly',
            'price'    => 24.99,
            'days'     => 90,
            'features' => ['All Monthly features', 'Analytics dashboard', 'Discount badge on profile'],
        ],
        'annual'    => [
            'name'     => 'Annual',
            'price'    => 79.99,
            'days'     => 365,
            'features' => ['All Quarterly features', 'Top mentor badge', 'Zero platform commission'],
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
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:monthly,quarterly,annual',
        ]);

        $user = $request->user();
        $plan = $this->plans[$request->plan];

        // Cancel any existing active subscription
        $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

        $subscription = Subscription::create([
            'user_id'    => $user->id,
            'plan'       => $request->plan,
            'price'      => $plan['price'],
            'starts_at'  => now(),
            'expires_at' => now()->addDays($plan['days']),
            'status'     => 'active',
        ]);

        // Feature the mentor profile after subscribing
        $user->mentorProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['is_featured' => true]
        );

        return ApiResponse::created([
            'subscription'            => $subscription,
            'has_active_subscription' => true,
            'can_create_sessions'     => true,
        ], "Subscribed to {$plan['name']} plan. You can now create sessions.");
    }

    public function cancel(Request $request): JsonResponse
    {
        $active = $request->user()->activeSubscription()->first();

        if (!$active) {
            return ApiResponse::error('No active subscription found.');
        }

        $active->update(['status' => 'cancelled']);

        $request->user()->mentorProfile()->update(['is_featured' => false]);

        return ApiResponse::success([
            'has_active_subscription' => false,
            'can_create_sessions'     => false,
        ], 'Subscription cancelled. You can no longer create new sessions.');
    }
}
