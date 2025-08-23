<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int|null $employee_id Foreign key to Employees
 * @property int|null $department_id Foreign key to Departments
 * @property string $schedule_name Name of the schedule
 * @property Carbon|null $lunch_start_time
 * @property Carbon|null $lunch_stop_time
 * @property Carbon|null $start_time
 * @property int $lunch_duration Lunch duration in minutes
 * @property int $daily_hours
 * @property Carbon|null $end_time
 * @property int $grace_period Allowed grace period in minutes for lateness
 * @property int|null $shift_id Reference to the shift
 * @property bool $is_active Indicates if the schedule is active
 * @property string|null $notes Additional notes for the schedule
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department|null $department
 * @property-read Employee|null $employee
 * @property-read Shift|null $shift
 * @method static Builder<static>|ShiftSchedule active()
 * @method static Builder<static>|ShiftSchedule newModelQuery()
 * @method static Builder<static>|ShiftSchedule newQuery()
 * @method static Builder<static>|ShiftSchedule query()
 * @method static Builder<static>|ShiftSchedule whereCreatedAt($value)
 * @method static Builder<static>|ShiftSchedule whereCreatedBy($value)
 * @method static Builder<static>|ShiftSchedule whereDailyHours($value)
 * @method static Builder<static>|ShiftSchedule whereDepartmentId($value)
 * @method static Builder<static>|ShiftSchedule whereEmployeeId($value)
 * @method static Builder<static>|ShiftSchedule whereEndTime($value)
 * @method static Builder<static>|ShiftSchedule whereGracePeriod($value)
 * @method static Builder<static>|ShiftSchedule whereId($value)
 * @method static Builder<static>|ShiftSchedule whereIsActive($value)
 * @method static Builder<static>|ShiftSchedule whereLunchDuration($value)
 * @method static Builder<static>|ShiftSchedule whereLunchStartTime($value)
 * @method static Builder<static>|ShiftSchedule whereNotes($value)
 * @method static Builder<static>|ShiftSchedule whereScheduleName($value)
 * @method static Builder<static>|ShiftSchedule whereShiftId($value)
 * @method static Builder<static>|ShiftSchedule whereStartTime($value)
 * @method static Builder<static>|ShiftSchedule whereUpdatedAt($value)
 * @method static Builder<static>|ShiftSchedule whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class ShiftSchedule extends Model
{
    use HasFactory;

    /**
     * Attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'department_id',
        'schedule_name',
        'lunch_start_time',
        'lunch_stop_time',
        'start_time',
        'lunch_duration',
        'daily_hours',
        'end_time',
        'grace_period',
        'shift_id',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * Automatically include related models.
     *
     * @var array<string>
     */
    protected $with = ['employee', 'department', 'shift'];

    /**
     * Attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'lunch_start_time' => 'datetime:H:i', // HH:MM format
        'lunch_stop_time' => 'datetime:H:i', // HH:MM format
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'grace_period' => 'integer',
    ];

    /**
     * Relationship: Employee.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    /**
     * Relationship: Department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    /**
     * Relationship: Shift.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Scope to get active schedules.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Automatically set the `created_by` and `updated_by` fields.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
