<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Employee model
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $zip
 * @property string|null $country
 * @property string|null $phone
 * @property string|null $external_id
 * @property int|null $department_id
 * @property int|null $round_group_id
 * @property int|null $payroll_frequency_id
 * @property Carbon|null $termination_date
 * @property bool $is_active
 * @property bool $full_time
 * @property bool $vacation_pay
 * @property-read string $full_name
 * @property-read Department|null $department
 * @property-read RoundingRule|null $roundingRule
 * @property-read PayrollFrequency|null $payrollFrequency
 * @property-read User|null $user
 * @property-read ShiftSchedule|null $schedule
 * @property string $external_department_id
 * @property string|null $email Employee email address
 * @property int|null $shift_id Foreign key referencing the shifts table
 * @property string|null $photograph Path or URL of the employee photograph
 * @property int|null $created_by Foreign key referencing the user who created the record
 * @property int|null $updated_by Foreign key referencing the user who last updated the record
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $full_names Concatenated full name of the employee
 * @property int|null $shift_schedule_id Foreign key referencing the shift schedules table
 * @property-read RoundGroup|null $roundGroup
 * @method static Builder<static>|Employee newModelQuery()
 * @method static Builder<static>|Employee newQuery()
 * @method static Builder<static>|Employee query()
 * @method static Builder<static>|Employee whereAddress($value)
 * @method static Builder<static>|Employee whereCity($value)
 * @method static Builder<static>|Employee whereCountry($value)
 * @method static Builder<static>|Employee whereCreatedAt($value)
 * @method static Builder<static>|Employee whereCreatedBy($value)
 * @method static Builder<static>|Employee whereDepartmentId($value)
 * @method static Builder<static>|Employee whereEmail($value)
 * @method static Builder<static>|Employee whereExternalDepartmentId($value)
 * @method static Builder<static>|Employee whereExternalId($value)
 * @method static Builder<static>|Employee whereFirstName($value)
 * @method static Builder<static>|Employee whereFullNames($value)
 * @method static Builder<static>|Employee whereFullTime($value)
 * @method static Builder<static>|Employee whereId($value)
 * @method static Builder<static>|Employee whereIsActive($value)
 * @method static Builder<static>|Employee whereLastName($value)
 * @method static Builder<static>|Employee wherePayrollFrequencyId($value)
 * @method static Builder<static>|Employee wherePhone($value)
 * @method static Builder<static>|Employee wherePhotograph($value)
 * @method static Builder<static>|Employee whereRoundGroupId($value)
 * @method static Builder<static>|Employee whereShiftId($value)
 * @method static Builder<static>|Employee whereShiftScheduleId($value)
 * @method static Builder<static>|Employee whereState($value)
 * @method static Builder<static>|Employee whereTerminationDate($value)
 * @method static Builder<static>|Employee whereUpdatedAt($value)
 * @method static Builder<static>|Employee whereUpdatedBy($value)
 * @method static Builder<static>|Employee whereVacationPay($value)
 * @method static Builder<static>|Employee whereZip($value)
 * @mixin \Eloquent
 */
class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'external_id',
        'external_department_id',
        'department_id',
        'shift_id',
        'round_group_id',
        'photograph',
        'termination_date',
        'is_active',
        'full_time',
        'vacation_pay',
        'created_at',
        'updated_at',
        'payroll_frequency_id',
        'full_names',
        'shift_schedule_id',
    ];

    protected $casts = [
        'termination_date' => 'date',
        'is_active' => 'boolean',
        'full_time' => 'boolean',
        'vacation_pay' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function roundGroup(): BelongsTo
    {
        return $this->belongsTo(RoundGroup::class, 'round_group_id');
    }

    public function payrollFrequency(): BelongsTo
    {
        return $this->belongsTo(PayrollFrequency::class, 'payroll_frequency_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(ShiftSchedule::class, 'employee_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

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
