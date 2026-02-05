<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class IntegrationConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'driver',
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
        'created_by',
        'updated_by',
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
    ];

    protected $hidden = [
        'auth_credentials',
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
        } catch (\Exception $e) {
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
        if (!$this->isPollingEnabled()) {
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

    public function syncLogs(): HasMany
    {
        return $this->hasMany(IntegrationSyncLog::class, 'connection_id');
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
}
