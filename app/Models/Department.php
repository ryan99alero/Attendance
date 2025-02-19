<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 
 *
 * @property int $id
 * @property string $name Department name
 * @property int|null $manager_id Foreign key to Employees for department manager
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, Employee> $employees
 * @property-read int|null $employees_count
 * @property-read Employee|null $manager
 * @property-read Collection<int, ShiftSchedule> $schedules
 * @property-read int|null $schedules_count
 * @property-read User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereManagerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereUpdatedBy($value)
 * @property string|null $external_department_id ID from external Department systems
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereExternalDepartmentId($value)
 * @mixin \Eloquent
 */
class Department extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'manager_id',
        'external_department_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the manager of the department.
     */
    public function manager(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Get the user who created the department.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the department.
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    /**
     * Relationship: schedule.
    */
    public function schedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShiftSchedule::class, 'department_id', 'id');
    }

    /**
     * Get the employees that belong to the department.
     */
    public function employees(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Employee::class, 'department_id');
    }
}
