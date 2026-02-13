<?php

namespace App\Models;

use App\Jobs\ProcessClockEventJob;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClockEvent extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function (ClockEvent $clockEvent) {
            // Only dispatch job if event has an employee (valid credential)
            // and queue processing is enabled
            if ($clockEvent->employee_id && static::shouldProcessViaQueue()) {
                ProcessClockEventJob::dispatch($clockEvent);
            }
        });
    }

    /**
     * Check if queue processing is enabled based on company settings
     */
    protected static function shouldProcessViaQueue(): bool
    {
        $companySetup = CompanySetup::first();

        // If no company setup, default to queue processing
        if (! $companySetup) {
            return true;
        }

        // Don't use queue if set to manual_only
        return $companySetup->clock_event_sync_frequency !== 'manual_only';
    }

    protected $fillable = [
        'employee_id',
        'device_id',
        'credential_id',
        'event_time',
        'shift_date',
        'event_source',
        'location',
        'confidence',
        'raw_payload',
        'notes',
        'created_by',
        'updated_by',
        'batch_id',
        'processing_error',
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
                $eventTime->copy()->addSeconds($windowSeconds),
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
            Carbon::parse($endDate)->endOfDay(),
        ]);
    }

    /**
     * Scope for events by source
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('event_source', $source);
    }

    /**
     * Scope for events with processing errors
     */
    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('processing_error');
    }

    /**
     * Scope for events ready for processing (has employee and no error)
     *
     * Note: All events in this table are "unprocessed" by definition.
     * Successfully processed events are deleted from this table.
     */
    public function scopeReadyForProcessing($query)
    {
        return $query->whereNotNull('employee_id')
            ->whereNull('processing_error');
    }
}
