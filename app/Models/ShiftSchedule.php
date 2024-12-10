<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
