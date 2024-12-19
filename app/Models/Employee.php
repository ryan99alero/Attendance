<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 *
 *
 * @property int $id
 * @property string $first_name First name of the employee
 * @property string $last_name Last name of the employee
 * @property string|null $address Employee address
 * @property string|null $city City of the employee
 * @property string|null $state State of the employee
 * @property string|null $zip ZIP code of the employee
 * @property string|null $country Country of the employee
 * @property string|null $phone Phone number of the employee
 * @property string|null $external_id External ID of the employee
 * @property int|null $department_id Foreign key to Departments
 * @property int|null $shift_id Foreign key to Shifts
 * @property int|null $rounding_method Foreign key to Rounding Rules
 * @property string|null $photograph Photograph path or URL
 * @property \Illuminate\Support\Carbon|null $termination_date Termination date of the employee
 * @property bool $is_active Indicates if the employee is active
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $payroll_frequency_id Foreign key to Payroll Frequencies
 * @property string|null $full_names
 * @property int|null $shift_schedule_id Foreign key to shift_schedules
 * @property-read \App\Models\RoundingRule|null $RoundingRule
 * @property-read \App\Models\Department|null $department
 * @property-read string $full_name
 * @property-read \App\Models\PayrollFrequency|null $payrollFrequency
 * @property-read \App\Models\ShiftSchedule|null $schedule
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereFullNames($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee wherePayrollFrequencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee wherePhotograph($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereRoundingMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereShiftScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereTerminationDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereZip($value)
 * @mixin \Eloquent
 */
class Employee extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'full_names',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'external_id',
        'department_id',
        'rounding_method',
        'payroll_frequency_id',
        'termination_date',
        'is_active',
        'full_time',
        'vacation_pay',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'termination_date' => 'date',
        'is_active' => 'boolean',
        'full_time' => 'boolean',
        'vacation_pay' => 'boolean',

    ];

    /**
     * Relationship: Department.
     *
     * @return BelongsTo
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
    /**
     * Relationship: RoundingRule.
     *
     * @return BelongsTo
     */
    public function RoundingRule(): BelongsTo
    {
        return $this->belongsTo(RoundingRule::class, 'id');
    }

    /**
     * Relationship: Payroll Frequency.
     *
     * @return BelongsTo
     */
    public function payrollFrequency(): BelongsTo
    {
        return $this->belongsTo(PayrollFrequency::class, 'payroll_frequency_id');
    }
    /**
     * Relationship: ShiftSchedule.
     *
     * @return HasOne
     */
    public function schedule(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ShiftSchedule::class, 'employee_id', 'id');
    }

    /**
     * Relationship: User.
     *
     * @return HasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    /**
     * Accessor: Full Name.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
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
