<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ClockEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'device_id',
        'credential_id',
        'punch_type_id',
        'event_time',
        'shift_date',
        'event_source',
        'location',
        'confidence',
        'raw_payload',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'shift_date' => 'date',
        'confidence' => 'integer',
        'raw_payload' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function punchType(): BelongsTo
    {
        return $this->belongsTo(PunchType::class);
    }

    /**
     * Check for duplicate events within a time window
     */
    public static function hasDuplicateWithin(
        int $deviceId,
        int $credentialId,
        Carbon $eventTime,
        int $windowSeconds = 10
    ): bool {
        return static::where('device_id', $deviceId)
                    ->where('credential_id', $credentialId)
                    ->whereBetween('event_time', [
                        $eventTime->copy()->subSeconds($windowSeconds),
                        $eventTime->copy()->addSeconds($windowSeconds)
                    ])
                    ->exists();
    }

    /**
     * Scope for events within a date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('event_time', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay()
        ]);
    }

    /**
     * Scope for events by source
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('event_source', $source);
    }
}