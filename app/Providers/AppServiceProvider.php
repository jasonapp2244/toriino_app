<?php

namespace App\Providers;

use App\Services\MuxService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MuxService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! app()->isProduction()) {
            Model::shouldBeStrict();
        }

        if (! app()->environment('local')) {
            URL::forceScheme('https');
        }

        if ($key = config('services.stripe.secret')) {
            \Stripe\Stripe::setApiKey($key);
        }

        // Global API rate limiter: 120 requests/minute per user (or IP for guests)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
