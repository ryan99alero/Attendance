<?php

namespace App\Models;

use App\Services\AttendanceProcessing\AttendanceProcessingService;
use App\Services\ClockEventProcessing\ClockEventProcessingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Log;

/**
 * Class PayPeriod
 *
 * @property int $id
 * @property Carbon $start_date Start date of the pay period
 * @property Carbon $end_date End date of the pay period
 * @property bool $is_processed Indicates if the pay period has been processed
 * @property int|null $processed_by Foreign key to Users for processor
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read User|null $processor
 * @property-read Collection<int, Punch> $punches
 * @property-read int|null $punches_count
 * @property-read User|null $updater
 *
 * @method static Builder|PayPeriod newModelQuery()
 * @method static Builder|PayPeriod newQuery()
 * @method static Builder|PayPeriod query()
 * @method static Builder|PayPeriod whereCreatedAt($value)
 * @method static Builder|PayPeriod whereCreatedBy($value)
 * @method static Builder|PayPeriod whereEndDate($value)
 * @method static Builder|PayPeriod whereId($value)
 * @method static Builder|PayPeriod whereIsProcessed($value)
 * @method static Builder|PayPeriod whereProcessedBy($value)
 * @method static Builder|PayPeriod whereStartDate($value)
 * @method static Builder|PayPeriod whereUpdatedAt($value)
 * @method static Builder|PayPeriod whereUpdatedBy($value)
 *
 * @property int $is_posted Indicates if the pay period has been processed
 *
 * @method static Builder<static>|PayPeriod whereIsPosted($value)
 *
 * @mixin \Eloquent
 */
class PayPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'name',
        'is_processed',
        'is_posted',
        'processing_status',
        'processing_progress',
        'processing_message',
        'total_employees',
        'processed_employees',
        'processing_error',
        'processing_started_at',
        'processing_completed_at',
        'processed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_processed' => 'boolean',
        'is_posted' => 'boolean',
        'processing_progress' => 'integer',
        'total_employees' => 'integer',
        'processed_employees' => 'integer',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
    ];

    public function isProcessing(): bool
    {
        return $this->processing_status === 'processing';
    }

    public function hasProcessingFailed(): bool
    {
        return $this->processing_status === 'failed';
    }

    public function hasProcessingCompleted(): bool
    {
        return $this->processing_status === 'completed';
    }

    public function updateProgress(int $progress, string $message, ?int $processed = null): void
    {
        $data = [
            'processing_progress' => min(100, max(0, $progress)),
            'processing_message' => $message,
        ];

        if ($processed !== null) {
            $data['processed_employees'] = $processed;
        }

        $this->update($data);
    }

    public function getProgressText(): string
    {
        if ($this->hasProcessingCompleted()) {
            return 'Processing complete';
        }

        if ($this->hasProcessingFailed()) {
            return "Failed: {$this->processing_error}";
        }

        if ($this->total_employees && $this->processed_employees) {
            return "{$this->processing_message} ({$this->processed_employees}/{$this->total_employees})";
        }

        return $this->processing_message ?? 'Starting...';
    }

    // Relationships
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function punches(): HasMany
    {
        return $this->hasMany(Punch::class, 'pay_period_id');
    }

    public function employeeSummaries(): HasMany
    {
        return $this->hasMany(PayPeriodEmployeeSummary::class);
    }

    public function payrollExports(): HasMany
    {
        return $this->hasMany(PayrollExport::class);
    }

    public function attendanceIssues(): Builder
    {
        return Attendance::query()
            ->whereBetween('punch_time', [
                $this->start_date->startOfDay(),
                $this->end_date->endOfDay(),
            ])
            ->where(function ($query) {
                $query->where('status', 'NeedsReview')
                    ->orWhere(function ($subQuery) {
                        // Only show Incomplete records that have been processed (have punch_type_id assigned)
                        // but still have issues, not those that are simply unprocessed
                        $subQuery->where('status', 'Incomplete')
                            ->whereNotNull('punch_type_id');
                    });
            });
    }

    // Custom Methods
    public function attendanceIssuesCount(): int
    {
        return $this->attendanceIssues()->count();
    }

    public function punchCount(): int
    {
        // Count all attendance records that have punch_type_id assigned (processed records)
        // This includes 'Complete', 'Migrated', and 'Posted' records
        return Attendance::whereBetween('punch_time', [
            $this->start_date->startOfDay(),
            $this->end_date->endOfDay(),
        ])
            ->whereNotNull('punch_type_id')
            ->whereIn('status', ['Complete', 'Migrated', 'Posted'])
            ->count();
    }

    public function consensusDisagreementCount(): int
    {
        return Attendance::whereBetween('punch_time', [
            $this->start_date->startOfDay(),
            $this->end_date->endOfDay(),
        ])
            ->where('status', 'Discrepancy')
            ->count();
    }

    public function processAttendance(): int
    {
        // Step 1: Process any unprocessed ClockEvents for this pay period first
        $clockEventService = app(ClockEventProcessingService::class);

        // Get ClockEvents within this pay period that are ready for processing
        $clockEventsInPeriod = ClockEvent::readyForProcessing()
            ->whereBetween('event_time', [
                $this->start_date->startOfDay(),
                $this->end_date->endOfDay(),
            ])
            ->count();

        if ($clockEventsInPeriod > 0) {
            Log::info("[PayPeriod] Processing {$clockEventsInPeriod} ClockEvents for PayPeriod {$this->id}");
            $clockEventResult = $clockEventService->processUnprocessedEvents(500); // Process all events
            Log::info('[PayPeriod] ClockEvent processing result', $clockEventResult);
        }

        // Step 2: Process Attendance records (assign punch types, ML analysis, etc.)
        $attendanceService = app(AttendanceProcessingService::class);
        $attendanceService->processAll($this);

        return $clockEventsInPeriod;
    }

    public static function current()
    {
        return self::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }
}
