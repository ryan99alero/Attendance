<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorReferenceService
{
    /**
     * Generate a unique error reference code.
     * Format: ERR-YYYYMMDD-XXXXXX (date + 6 char alphanumeric)
     */
    public static function generate(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        return "ERR-{$date}-{$random}";
    }

    /**
     * Log an exception with a reference code.
     * Returns the reference code for display to users.
     */
    public static function logException(Throwable $e, ?string $referenceCode = null): string
    {
        $referenceCode ??= self::generate();

        Log::error("[{$referenceCode}] {$e->getMessage()}", [
            'reference_code' => $referenceCode,
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'trace' => $e->getTraceAsString(),
        ]);

        return $referenceCode;
    }

    /**
     * Get user-friendly message for common HTTP status codes.
     */
    public static function getFriendlyMessage(int $statusCode): array
    {
        return match ($statusCode) {
            400 => [
                'title' => 'Bad Request',
                'message' => 'The request could not be understood. Please check your input and try again.',
            ],
            401 => [
                'title' => 'Unauthorized',
                'message' => 'You need to sign in to access this page.',
            ],
            403 => [
                'title' => 'Access Denied',
                'message' => "You don't have permission to access this resource.",
            ],
            404 => [
                'title' => 'Page Not Found',
                'message' => "The page you're looking for doesn't exist or has been moved.",
            ],
            405 => [
                'title' => 'Method Not Allowed',
                'message' => 'This action is not supported. If you followed a link, please contact support.',
            ],
            419 => [
                'title' => 'Session Expired',
                'message' => 'Your session has expired. Please refresh the page and try again.',
            ],
            429 => [
                'title' => 'Too Many Requests',
                'message' => "You've made too many requests. Please wait a moment and try again.",
            ],
            500 => [
                'title' => 'Server Error',
                'message' => 'Something went wrong on our end. Our team has been notified.',
            ],
            503 => [
                'title' => 'Service Unavailable',
                'message' => "We're currently performing maintenance. Please check back shortly.",
            ],
            default => [
                'title' => 'Error',
                'message' => 'An unexpected error occurred. Please try again or contact support.',
            ],
        };
    }
}
