<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int|null $employee_id Foreign key to Employees
 * @property int|null $device_id Foreign key to Devices
 * @property int|null $punch_type_id Foreign key to Punch Types
 * @property Carbon|null $punch_time
 * @property bool $is_altered Indicates if the punch was altered post-recording
 * @property bool $is_late
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $pay_period_id Foreign key to Pay Periods
 * @property int|null $attendance_id Foreign key to Attendances
 * @property-read User|null $creator
 * @property-read Device|null $device
 * @property-read Employee|null $employee
 * @property-read PayPeriod|null $payPeriod
 * @property-read PunchType|null $punchType
 * @property-read User|null $updater
 * @method static Builder<static>|Punch forShiftsCrossingMidnight($employeeId)
 * @method static Builder<static>|Punch newModelQuery()
 * @method static Builder<static>|Punch newQuery()
 * @method static Builder<static>|Punch query()
 * @method static Builder<static>|Punch whereAttendanceId($value)
 * @method static Builder<static>|Punch whereCreatedAt($value)
 * @method static Builder<static>|Punch whereCreatedBy($value)
 * @method static Builder<static>|Punch whereDeviceId($value)
 * @method static Builder<static>|Punch whereEmployeeId($value)
 * @method static Builder<static>|Punch whereId($value)
 * @method static Builder<static>|Punch whereIsAltered($value)
 * @method static Builder<static>|Punch whereIsLate($value)
 * @method static Builder<static>|Punch wherePayPeriodId($value)
 * @method static Builder<static>|Punch wherePunchTime($value)
 * @method static Builder<static>|Punch wherePunchTypeId($value)
 * @method static Builder<static>|Punch whereUpdatedAt($value)
 * @method static Builder<static>|Punch whereUpdatedBy($value)
 * @property int|null $classification_id Foreign key referencing the classifications table
 * @property int $is_processed
 * @property string $external_group_id Links to attendance_time_groups.external_group_id
 * @property string|null $shift_date The assigned workday for this punch record
 * @property int $is_archived Indicates if record is archived
 * @method static Builder<static>|Punch whereClassificationId($value)
 * @method static Builder<static>|Punch whereExternalGroupId($value)
 * @method static Builder<static>|Punch whereIsArchived($value)
 * @method static Builder<static>|Punch whereIsProcessed($value)
 * @method static Builder<static>|Punch whereShiftDate($value)
 * @mixin \Eloquent
 */
class Punch extends Model
{

    protected $fillable = [
        'employee_id',
        'device_id',
        'punch_type_id',
        'punch_state',
        'punch_time',
        'classification_id',
        'pay_period_id', // Ensure this is included
        'attendance_id',
        'is_altered',
        'is_late',
        'created_by',
        'updated_by',
        'external_group_id',
        'shift_date',
    ];

    protected $casts = [
        'punch_time' => 'datetime',
        'is_altered' => 'boolean',
        'is_late' => 'boolean',
    ];

    /**
     * Automatically flag 'is_altered' when a record is updated.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updating(function ($punch) {
            $dirtyFields = $punch->getDirty();

            // Ignore changes to 'is_altered'
            if (array_key_exists('is_altered', $dirtyFields)) {
                unset($dirtyFields['is_altered']);
            }

            // If any other field was changed, set 'is_altered' to true
            if (!empty($dirtyFields)) {
                $punch->is_altered = true;
            }
        });
    }

    /**
     * Relationship with Employee model.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Relationship with Device model.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * Relationship with PunchType model.
     */
    public function punchType(): BelongsTo
    {
        return $this->belongsTo(PunchType::class, 'punch_type_id');
    }

    /**
     * Relationship with User model for record creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with User model for record updater.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relationship with PayPeriod model for Record Fetch.
     */
    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    /**
     * Scope for shifts crossing midnight, grouping punches within a 24-hour window.
     *
     * @param Builder $query
     * @param int $employeeId
     * @return Builder
     */
    public function scopeForShiftsCrossingMidnight($query, $employeeId)
    {
        // Get the employee's shift start time from the ShiftSchedule table
        $referenceTime = ShiftSchedule::where('employee_id', $employeeId)
            ->orWhere('department_id', function ($query) use ($employeeId) {
                $query->select('department_id')
                    ->from('employees')
                    ->where('id', $employeeId);
            })
            ->value('start_time') ?? '06:00:00'; // Default to 6:00 AM if no schedule found

        return $query->whereRaw("
        TIME(punch_time) >= ?
        OR TIME(punch_time) < ?
    ", [$referenceTime, $referenceTime])
            ->orderBy('employee_id')
            ->orderBy('punch_time');
    }
}
