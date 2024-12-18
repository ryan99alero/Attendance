<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $employee_id Foreign key to Employees
 * @property numeric $accrual_rate Rate at which vacation time accrues per pay period
 * @property numeric $accrued_hours Total vacation hours accrued
 * @property numeric $used_hours Total vacation hours used
 * @property numeric $carry_over_hours Vacation hours carried over from the previous year
 * @property numeric $cap_hours Maximum allowed vacation hours (cap)
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Employee $employee
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereAccrualRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereAccruedHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereCapHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereCarryOverHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereUsedHours($value)
 * @mixin \Eloquent
 */
class VacationBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'accrual_rate',
        'accrued_hours',
        'used_hours',
        'carry_over_hours',
        'cap_hours',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'accrual_rate' => 'decimal:2',
        'accrued_hours' => 'decimal:2',
        'used_hours' => 'decimal:2',
        'carry_over_hours' => 'decimal:2',
        'cap_hours' => 'decimal:2',
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
