<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Crypt;

class IntegrationConnection extends Model
{
    use HasFactory;

    // Integration method constants
    public const METHOD_API = 'api';

    public const METHOD_FLATFILE = 'flatfile';

    // Driver constants
    public const DRIVER_PACE = 'pace';

    public const DRIVER_ADP = 'adp';

    public const DRIVER_QUICKBOOKS = 'quickbooks';

    public const DRIVER_GENERIC_REST = 'generic_rest';

    protected $fillable = [
        'name',
        'driver',
        'integration_method',
        'base_url',
        'api_version',
        'auth_type',
        'auth_credentials',
        'timeout_seconds',
        'retry_attempts',
        'rate_limit_per_minute',
        'is_active',
        'last_connected_at',
        'last_error_at',
        'last_error_message',
        'sync_interval_minutes',
        'last_synced_at',
        'webhook_token',
        'created_by',
        'updated_by',
        // Payroll provider fields
        'is_payroll_provider',
        'export_formats',
        'export_destination',
        'export_path',
        'export_filename_pattern',
        // ADP-specific fields
        'adp_company_code',
        'adp_batch_format',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_connected_at' => 'datetime',
        'last_error_at' => 'datetime',
        'timeout_seconds' => 'integer',
        'retry_attempts' => 'integer',
        'rate_limit_per_minute' => 'integer',
        'sync_interval_minutes' => 'integer',
        'last_synced_at' => 'datetime',
        // Payroll provider casts
        'is_payroll_provider' => 'boolean',
        'export_formats' => 'array',
    ];

    protected $hidden = [
        'auth_credentials',
        'webhook_token',
    ];

    /**
     * Get decrypted credentials
     */
    public function getCredentials(): array
    {
        if (empty($this->auth_credentials)) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($this->auth_credentials), true) ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Set encrypted credentials
     */
    public function setCredentials(array $credentials): void
    {
        $this->auth_credentials = Crypt::encryptString(json_encode($credentials));
    }

    /**
     * Get a specific credential value
     */
    public function getCredential(string $key, $default = null)
    {
        return $this->getCredentials()[$key] ?? $default;
    }

    /**
     * Record a successful connection
     */
    public function markConnected(): void
    {
        $this->update([
            'last_connected_at' => now(),
            'last_error_at' => null,
            'last_error_message' => null,
        ]);
    }

    /**
     * Record a connection error
     */
    public function markError(string $message): void
    {
        $this->update([
            'last_error_at' => now(),
            'last_error_message' => $message,
        ]);
    }

    /**
     * Check if connection has recent errors
     */
    public function hasRecentErrors(int $minutesThreshold = 60): bool
    {
        return $this->last_error_at && $this->last_error_at->diffInMinutes(now()) < $minutesThreshold;
    }

    // Sync scheduling

    public function isPollingEnabled(): bool
    {
        return $this->is_active && $this->sync_interval_minutes > 0;
    }

    public function isDueForSync(): bool
    {
        if (! $this->isPollingEnabled()) {
            return false;
        }

        if ($this->last_synced_at === null) {
            return true;
        }

        return $this->last_synced_at->addMinutes($this->sync_interval_minutes)->isPast();
    }

    public function markSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }

    public function isPushMode(): bool
    {
        return $this->sync_interval_minutes <= 0;
    }

    public function generateWebhookToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->update(['webhook_token' => $token]);

        return $token;
    }

    public function getOrCreateWebhookToken(): string
    {
        if (! empty($this->webhook_token)) {
            return $this->webhook_token;
        }

        return $this->generateWebhookToken();
    }

    public function getWebhookUrl(?string $objectName = null): string
    {
        $token = $this->getOrCreateWebhookToken();
        $base = url("/api/webhooks/sync/{$token}");

        return $objectName ? "{$base}/{$objectName}" : $base;
    }

    public function scopePollingEnabled($query)
    {
        return $query->where('is_active', true)->where('sync_interval_minutes', '>', 0);
    }

    // Relationships

    public function objects(): HasMany
    {
        return $this->hasMany(IntegrationObject::class, 'connection_id');
    }

    public function queryTemplates(): HasMany
    {
        return $this->hasMany(IntegrationQueryTemplate::class, 'connection_id');
    }

    public function syncLogs(): MorphMany
    {
        return $this->morphMany(SystemLog::class, 'loggable')
            ->where('category', SystemLog::CATEGORY_INTEGRATION);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDriver($query, string $driver)
    {
        return $query->where('driver', $driver);
    }

    public function scopePayrollProviders($query)
    {
        return $query->where('is_payroll_provider', true);
    }

    // Payroll provider relationships

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'payroll_provider_id');
    }

    public function payrollExports(): HasMany
    {
        return $this->hasMany(PayrollExport::class);
    }

    // Payroll provider helper methods

    public function supportsFormat(string $format): bool
    {
        return in_array($format, $this->export_formats ?? []);
    }

    public function getEnabledFormats(): array
    {
        return $this->export_formats ?? [];
    }

    // Integration method helpers

    public function isApiMethod(): bool
    {
        return $this->integration_method === self::METHOD_API;
    }

    public function isFlatFileMethod(): bool
    {
        return $this->integration_method === self::METHOD_FLATFILE;
    }

    public static function getIntegrationMethods(): array
    {
        return [
            self::METHOD_API => 'API Integration',
            self::METHOD_FLATFILE => 'Flat File Export',
        ];
    }

    /**
     * Get all integration types as a single unified list.
     * Each entry sets both driver and integration_method internally.
     */
    public static function getIntegrationTypes(): array
    {
        return [
            // API integrations
            'pace_api' => 'Pace / ePace ERP (API)',
            'adp_api' => 'ADP Workforce Now (API)',
            'quickbooks_api' => 'QuickBooks Online (API)',
            'generic_api' => 'Generic REST API',

            // Flat file exports
            'adp_file' => 'ADP File Export',
            'csv_export' => 'CSV Export',
            'excel_export' => 'Excel Export',
        ];
    }

    /**
     * Parse integration type into driver and method.
     */
    public static function parseIntegrationType(string $type): array
    {
        return match ($type) {
            'pace_api' => ['driver' => self::DRIVER_PACE, 'method' => self::METHOD_API],
            'adp_api' => ['driver' => self::DRIVER_ADP, 'method' => self::METHOD_API],
            'quickbooks_api' => ['driver' => self::DRIVER_QUICKBOOKS, 'method' => self::METHOD_API],
            'generic_api' => ['driver' => self::DRIVER_GENERIC_REST, 'method' => self::METHOD_API],
            'adp_file' => ['driver' => self::DRIVER_ADP, 'method' => self::METHOD_FLATFILE],
            'csv_export' => ['driver' => 'csv', 'method' => self::METHOD_FLATFILE],
            'excel_export' => ['driver' => 'excel', 'method' => self::METHOD_FLATFILE],
            default => ['driver' => $type, 'method' => self::METHOD_API],
        };
    }

    /**
     * Get the combined integration type key from driver + method.
     */
    public function getIntegrationType(): string
    {
        if ($this->integration_method === self::METHOD_FLATFILE) {
            return match ($this->driver) {
                self::DRIVER_ADP => 'adp_file',
                'csv' => 'csv_export',
                'excel' => 'excel_export',
                default => 'csv_export',
            };
        }

        return match ($this->driver) {
            self::DRIVER_PACE => 'pace_api',
            self::DRIVER_ADP => 'adp_api',
            self::DRIVER_QUICKBOOKS => 'quickbooks_api',
            self::DRIVER_GENERIC_REST => 'generic_api',
            default => 'generic_api',
        };
    }
}
