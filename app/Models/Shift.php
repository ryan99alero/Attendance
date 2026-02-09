<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property string $shift_name Name of the shift
 * @property Carbon $start_time Scheduled start time of the shift
 * @property Carbon $end_time Scheduled end time of the shift
 * @property int|null $base_hours_per_period Standard hours for the shift per pay period
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, ShiftSchedule> $schedules
 * @property-read int|null $schedules_count
 * @property-read User|null $updater
 * @method static Builder<static>|Shift newModelQuery()
 * @method static Builder<static>|Shift newQuery()
 * @method static Builder<static>|Shift query()
 * @method static Builder<static>|Shift whereBaseHoursPerPeriod($value)
 * @method static Builder<static>|Shift whereCreatedAt($value)
 * @method static Builder<static>|Shift whereCreatedBy($value)
 * @method static Builder<static>|Shift whereEndTime($value)
 * @method static Builder<static>|Shift whereId($value)
 * @method static Builder<static>|Shift whereShiftName($value)
 * @method static Builder<static>|Shift whereStartTime($value)
 * @method static Builder<static>|Shift whereUpdatedAt($value)
 * @method static Builder<static>|Shift whereUpdatedBy($value)
 * @property int $multi_day_shift Indicates if the shift spans multiple calendar days
 * @method static Builder<static>|Shift whereMultiDayShift($value)
 * @mixin \Eloquent
 */
class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_name',
        'start_time',
        'multi_day_shift',
        'end_time',
        'base_hours_per_period',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
