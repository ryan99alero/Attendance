<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $device_name Name of the device
 * @property string|null $ip_address IP address of the device
 * @property int $is_active Indicates if the device is active
 * @property int|null $department_id Foreign key to Departments
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Department|null $department
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereDeviceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class Device extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'device_id',
        'device_name',
        'display_name',
        'mac_address',
        'ip_address',
        'last_seen_at',
        'last_ip',
        'last_mac',
        'firmware_version',
        'last_wakeup_at',
        'is_active',
        'department_id',
        'created_by',
        'updated_by',
        // ESP32 Time Clock fields
        'device_type',
        'device_config',
        'api_token',
        'token_expires_at',
        'registration_status',
        'registration_notes',
        // Configuration and timezone fields
        'timezone',
        'ntp_server',
        'config_updated_at',
        'config_synced_at',
        'config_version',
        // Offline alerting
        'offline_alerted_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_wakeup_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'config_updated_at' => 'datetime',
        'config_synced_at' => 'datetime',
        'is_active' => 'boolean',
        'device_config' => 'array',
        'offline_alerted_at' => 'datetime',
    ];

    /**
     * Generate a new API token for the device
     */
    public function generateApiToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->update([
            'api_token' => hash('sha256', $token),
            'token_expires_at' => now()->addDays(30) // Token valid for 30 days
        ]);
        return $token; // Return the plain token for the device to store
    }

    /**
     * Check if the device token is valid
     */
    public function isTokenValid(string $token): bool
    {
        if (!$this->api_token || !$this->token_expires_at) {
            return false;
        }

        if ($this->token_expires_at->isPast()) {
            return false;
        }

        return hash_equals($this->api_token, hash('sha256', $token));
    }

    /**
     * Check if device is approved for operation
     */
    public function isApproved(): bool
    {
        return $this->registration_status === 'approved' && $this->is_active;
    }

    /**
     * Update device last seen timestamp
     */
    public function markAsSeen(): void
    {
        $this->update([
            'last_seen_at' => now(),
            'last_ip' => request()->ip(),
        ]);
    }

    /**
     * Scope for active time clocks
     */
    public function scopeTimeClocks($query)
    {
        return $query->where('device_type', 'esp32_timeclock');
    }

    /**
     * Scope for approved devices
     */
    public function scopeApproved($query)
    {
        return $query->where('registration_status', 'approved')->where('is_active', true);
    }

    /**
     * The department to which the device belongs.
     */
    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the user who created the device.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the device.
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if device is currently in offline alert state.
     */
    public function isOfflineAlerted(): bool
    {
        return $this->offline_alerted_at !== null;
    }

    /**
     * Scope for devices that have triggered offline alerts.
     */
    public function scopeOfflineAlerted($query)
    {
        return $query->whereNotNull('offline_alerted_at');
    }
}
