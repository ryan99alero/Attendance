<?php


namespace App\Services\Logging;

use Illuminate\Support\Facades\Log;
use App\Models\CompanySetup;

class AutoLogger
{
    protected static ?string $loggingLevel = null;

    /**
     * Get the logging level from CompanySetup (caches it to avoid multiple DB hits).
     */
    public static function getLoggingLevel (): string
    {
        if (is_null(self::$loggingLevel)) {
            self::$loggingLevel = CompanySetup::first()->logging_level ?? 'error';
        }

        return self::$loggingLevel;
    }

    /**
     * Log function calls automatically
     */
    public static function logFunctionCall (string $functionName, array $params = []): void
    {
        if (self::getLoggingLevel() === 'debug') {
            Log::debug("📌 Function Call: {$functionName} | Params: " . json_encode($params));
        }
    }

    /**
     * Log database query execution (used in AppServiceProvider)
     */
    public static function logDatabaseQuery (string $query, array $bindings, float $time): void
    {
        if (self::getLoggingLevel() === 'debug') {
            Log::debug("🛠 SQL Query: {$query} | Bindings: " . json_encode($bindings) . " | Time: {$time}ms");
        }
    }

    /**
     * Log model events like created, updated, deleted
     */
    public static function logModelEvent (string $model, string $event, array $changes = []): void
    {
        if (self::getLoggingLevel() === 'debug') {
            Log::debug("🔄 Model Event: {$model} - {$event} | Changes: " . json_encode($changes));
        }
    }

    /**
     * Log general messages based on level
     */
    public static function log (string $level, string $message, array $context = []): void
    {
        $currentLevel = self::getLoggingLevel();
        $levels = ['none' => 0, 'error' => 1, 'warning' => 2, 'info' => 3, 'debug' => 4];

        if ($levels[$level] <= $levels[$currentLevel]) {
            Log::$level($message, $context);
        }
    }
}
