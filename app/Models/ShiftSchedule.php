<?php

namespace App\Models;

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
 * @property \Illuminate\Support\Carbon|null $lunch_start_time
 * @property \Illuminate\Support\Carbon|null $start_time
 * @property int $lunch_duration Lunch duration in minutes
 * @property int $daily_hours
 * @property \Illuminate\Support\Carbon|null $end_time
 * @property int $grace_period Allowed grace period in minutes for lateness
 * @property int|null $shift_id Reference to the shift
 * @property bool $is_active Indicates if the schedule is active
 * @property string|null $notes Additional notes for the schedule
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Department|null $department
 * @property-read \App\Models\Employee|null $employee
 * @property-read \App\Models\Shift|null $shift
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereDailyHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereGracePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereLunchDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereLunchStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereScheduleName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSchedule whereUpdatedBy($value)
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
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
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
