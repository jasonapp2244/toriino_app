<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::unauthorized();
        }

        if (!$user->hasActiveSubscription()) {
            return ApiResponse::error(
                'An active subscription is required to access this feature.',
                402,
                ['subscription_required' => true]
            );
        }

        return $next($request);
    }
}
