<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Services\AttendanceProcessing\AttendanceProcessingService;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property int $is_posted Indicates if the pay period has been processed
 * @method static Builder<static>|PayPeriod whereIsPosted($value)
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
    public function attendanceIssues(): Builder
    {
        return Attendance::query()
            ->whereBetween('punch_time', [$this->start_date, $this->end_date])
            ->where('is_migrated', false); // Only include records where is_migrated is false
    }

    // Custom Methods
    public function attendanceIssuesCount(): int
    {
        return $this->attendanceIssues()->count();
    }

    public function punchCount(): int
    {
        return $this->punches()->count();
    }

    public function processAttendance(): int
    {
        $service = app(AttendanceProcessingService::class);
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
