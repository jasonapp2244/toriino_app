<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SubscriptionRequired;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'         => RoleMiddleware::class,
            'subscription' => SubscriptionRequired::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return ApiResponse::notFound('Resource not found.');
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            return ApiResponse::validationError($e->errors(), $e->getMessage());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return ApiResponse::unauthorized('Unauthenticated.');
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return ApiResponse::notFound('The requested URL was not found.');
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return ApiResponse::error('Too many requests. Please try again later.', 429);
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            $message = config('app.debug') ? $e->getMessage() : 'Server error.';

            return ApiResponse::error($message, 500);
        });
    })->create();
 