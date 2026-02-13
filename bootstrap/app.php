<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Prevent duplicate exception reports
        $exceptions->dontReportDuplicates();

        // Set appropriate log levels for different exception types
        $exceptions->level(PDOException::class, LogLevel::CRITICAL);
        $exceptions->level(RequestException::class, LogLevel::ERROR);

        // Don't report common non-error exceptions
        $exceptions->dontReport([
            ModelNotFoundException::class,
            NotFoundHttpException::class,
            ValidationException::class,
        ]);

        // Rate limit exception reporting to prevent log flooding
        $exceptions->throttle(function (\Throwable $e) {
            // Rate limit all exceptions to 100 per minute per type
            return Limit::perMinute(100)->by(get_class($e));
        });
    })->create();
