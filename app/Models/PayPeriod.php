<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod whereIsProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod whereProcessedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayPeriod whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class PayPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'is_processed',
        'processed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_processed' => 'boolean',
    ];

    /**
     * Relationship: Processor (User who processed the pay period).
     *
     * @return BelongsTo
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Relationship: Creator (User who created the record).
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Updater (User who last updated the record).
     *
     * @return BelongsTo
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relationship: Punches associated with the PayPeriod.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function punches(): HasMany
    {
        return $this->hasMany(Punch::class, 'pay_period_id');
    }

    /**
     * Virtual Relationship: Attendances with issues for this PayPeriod.
     * Query attendances where `punch_time` falls within the PayPeriod's range
     * and status is NOT 'Migrated'.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function attendanceIssues()
    {
        return Attendance::query()
            ->whereBetween('punch_time', [$this->start_date, $this->end_date])
            ->where('status', '!=', 'Migrated');
    }

    /**
     * Count of attendance issues for this PayPeriod.
     *
     * @return int
     */
    public function attendanceIssuesCount(): int
    {
        return $this->attendanceIssues()->count();
    }

    /**
     * Count of punches for this PayPeriod.
     *
     * @return int
     */
    public function punchCount(): int
    {
        return $this->punches()->count();
    }

    /**
     * Use the AttendanceProcessingService to process attendance.
     *
     * @return int
     */
    public function processAttendance(): int
    {
        $service = new \App\Services\AttendanceProcessing\AttendanceProcessingService();
        $service->processAll($this);

        return 0; // Return count if needed
    }
}
