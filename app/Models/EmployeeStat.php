<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $employee_id Foreign key to Employees
 * @property int $hours_worked Total hours worked
 * @property int $overtime_hours Total overtime hours
 * @property int $leave_days Total leave days
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Employee $employee
 * @property-read User|null $updater
 * @method static Builder<static>|EmployeeStat newModelQuery()
 * @method static Builder<static>|EmployeeStat newQuery()
 * @method static Builder<static>|EmployeeStat query()
 * @method static Builder<static>|EmployeeStat whereCreatedAt($value)
 * @method static Builder<static>|EmployeeStat whereCreatedBy($value)
 * @method static Builder<static>|EmployeeStat whereEmployeeId($value)
 * @method static Builder<static>|EmployeeStat whereHoursWorked($value)
 * @method static Builder<static>|EmployeeStat whereId($value)
 * @method static Builder<static>|EmployeeStat whereLeaveDays($value)
 * @method static Builder<static>|EmployeeStat whereOvertimeHours($value)
 * @method static Builder<static>|EmployeeStat whereUpdatedAt($value)
 * @method static Builder<static>|EmployeeStat whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class EmployeeStat extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'hours_worked',
        'overtime_hours',
        'leave_days',
        'created_by',
        'updated_by',
    ];

    /**
     * The employee to which the stats belong.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the user who created the record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
