<?php

use App\Services\ErrorReferenceService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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

        // Custom error page rendering with reference codes (only when debug is off)
        $exceptions->render(function (Throwable $e, Request $request) {
            // Only customize rendering when debug mode is off and it's not an API request
            if (config('app.debug') || $request->expectsJson()) {
                return null; // Use default handling
            }

            // Get the status code
            $statusCode = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            // Generate reference code for server errors (5xx) and log them
            $referenceCode = null;
            if ($statusCode >= 500) {
                $referenceCode = ErrorReferenceService::logException($e);
            } elseif ($statusCode >= 400 && $statusCode < 500) {
                // For 4xx errors, still generate a code but log at info level
                $referenceCode = ErrorReferenceService::generate();
                \Log::info("[{$referenceCode}] HTTP {$statusCode}: {$e->getMessage()}", [
                    'reference_code' => $referenceCode,
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => auth()->id(),
                ]);
            }

            // Check if a custom view exists for this status code
            $view = "errors.{$statusCode}";
            if (! view()->exists($view)) {
                $view = 'errors.500'; // Fallback to generic error
            }

            return response()->view($view, [
                'referenceCode' => $referenceCode,
                'exception' => $e,
            ], $statusCode);
        });
    })->create();
