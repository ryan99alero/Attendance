<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int|null $employee_id Foreign key to Employees
 * @property int|null $device_id Foreign key to Devices
 * @property int|null $punch_type_id Foreign key to Punch Types
 * @property \Illuminate\Support\Carbon|null $punch_time
 * @property bool $is_altered Indicates if the punch was altered post-recording
 * @property bool $is_late
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $pay_period_id Foreign key to Pay Periods
 * @property int|null $attendance_id Foreign key to Attendances
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Device|null $device
 * @property-read \App\Models\Employee|null $employee
 * @property-read \App\Models\PayPeriod|null $payPeriod
 * @property-read \App\Models\PunchType|null $punchType
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch forShiftsCrossingMidnight($employeeId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereAttendanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereIsAltered($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereIsLate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch wherePayPeriodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch wherePunchTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch wherePunchTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereUpdatedBy($value)
 * @property int|null $classification_id Foreign key referencing the classifications table
 * @property bool $is_posted
 * @property string $external_group_id Links to attendance_time_groups.external_group_id
 * @property string|null $shift_date The assigned workday for this punch record
 * @property int $is_archived Indicates if record is archived
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereClassificationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereExternalGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereIsArchived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereIsProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Punch whereShiftDate($value)
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
        'is_posted',
        'created_by',
        'updated_by',
        'external_group_id',
        'shift_date',
    ];

    protected $casts = [
        'punch_time' => 'datetime',
        'is_altered' => 'boolean',
        'is_late' => 'boolean',
        'is_posted' => 'boolean',
    ];

    /**
     * Automatically flag 'is_altered' when a record is updated and set created_by/updated_by fields.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($punch) {
            if (auth()->check()) {
                $punch->created_by = auth()->id();
            }
        });

        static::updating(function ($punch) {
            if (auth()->check()) {
                $punch->updated_by = auth()->id();
            }

            $dirtyFields = $punch->getDirty();

            // Ignore changes to 'is_altered' and 'updated_by'
            if (array_key_exists('is_altered', $dirtyFields)) {
                unset($dirtyFields['is_altered']);
            }
            if (array_key_exists('updated_by', $dirtyFields)) {
                unset($dirtyFields['updated_by']);
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
    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Relationship with Device model.
     */
    public function device(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * Relationship with PunchType model.
     */
    public function punchType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PunchType::class, 'punch_type_id');
    }

    /**
     * Relationship with User model for record creator.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with User model for record updater.
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relationship with PayPeriod model for Record Fetch.
     */
    public function payPeriod(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    /**
     * Relationship with Attendance model.
     */
    public function attendance(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    /**
     * Relationship with Classification model.
     */
    public function classification(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Classification::class, 'classification_id');
    }

    /**
     * Scope for shifts crossing midnight, grouping punches within a 24-hour window.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $employeeId
     * @return \Illuminate\Database\Eloquent\Builder
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
