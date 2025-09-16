<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class PayPeriod
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $start_date Start date of the pay period
 * @property \Illuminate\Support\Carbon $end_date End date of the pay period
 * @property bool $is_processed Indicates if the pay period has been processed
 * @property int|null $processed_by Foreign key to Users for processor
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $processor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Punch> $punches
 * @property-read int|null $punches_count
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod query()
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod whereIsProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod whereProcessedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayPeriod whereUpdatedBy($value)
 * @property int $is_posted Indicates if the pay period has been processed
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod whereIsPosted($value)
 * @mixin \Eloquent
 */
class PayPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'is_processed',
        'is_posted',
        'processed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_processed' => 'boolean',
        'is_posted' => 'boolean',
    ];

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
    public function attendanceIssues(): \Illuminate\Database\Eloquent\Builder
    {
        return Attendance::query()
            ->whereBetween('punch_time', [
                $this->start_date->startOfDay(),
                $this->end_date->endOfDay()
            ])
            ->where(function($query) {
                $query->where('status', 'NeedsReview')
                      ->orWhere(function($subQuery) {
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
            $this->end_date->endOfDay()
        ])
        ->whereNotNull('punch_type_id')
        ->whereIn('status', ['Complete', 'Migrated', 'Posted'])
        ->count();
    }

    public function consensusDisagreementCount(): int
    {
        return Attendance::whereBetween('punch_time', [
            $this->start_date->startOfDay(),
            $this->end_date->endOfDay()
        ])
        ->where('status', 'Discrepancy')
        ->count();
    }

    public function processAttendance(): int
    {
        $service = app(\App\Services\AttendanceProcessing\AttendanceProcessingService::class);
        $service->processAll($this);

        return 0; // Return count if needed
    }
    public static function current()
    {
        return self::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }
}
