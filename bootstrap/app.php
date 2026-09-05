<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->web(
            append: [
                \App\Http\Middleware\ResolveTenant::class,
            ],
            replace: [
                \Illuminate\Cookie\Middleware\EncryptCookies::class => \App\Http\Middleware\EncryptCookies::class,
            ],
        );
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'platform-admin' => \App\Http\Middleware\EnsureUserIsPlatformAdmin::class,
            'reservation-staff' => \App\Http\Middleware\EnsureUserIsReservationStaff::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
            'api-token' => \App\Http\Middleware\AuthenticateMobileToken::class,
            'api-role' => \App\Http\Middleware\EnsureMobileRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $exception): void {
            Log::channel('errors')->error($exception->getMessage(), [
                'exception' => $exception,
            ]);
        });
    })->create();
