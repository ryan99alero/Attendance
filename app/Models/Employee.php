<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $termination_date
 * @property bool $is_active
 * @property bool $full_time
 * @property bool $vacation_pay
 * @property-read string $full_name
 * @property-read Department|null $department
 * @property-read RoundingRule|null $roundingRule
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
 *
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
 *
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
        'address2',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'birth_date',
        'emergency_contact',
        'emergency_phone',
        'notes',
        'external_id',
        'external_department_id',
        'department_id',
        'shift_id',
        'round_group_id',
        'photograph',
        'termination_date',
        'is_active',
        'portal_clockin',
        'full_time',
        'created_at',
        'updated_at',
        'full_names',
        'shift_schedule_id',
        // Employment fields
        'date_of_hire',
        'seniority_date',
        'overtime_exempt',
        'overtime_rate',
        'double_time_threshold',
        'pay_type',
        'pay_rate',
        'payroll_provider_id',
    ];

    protected $casts = [
        'termination_date' => 'date',
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'portal_clockin' => 'boolean',
        'full_time' => 'boolean',
        // Employment field casts
        'date_of_hire' => 'date',
        'seniority_date' => 'date',
        'overtime_exempt' => 'boolean',
        'overtime_rate' => 'decimal:3',
        'double_time_threshold' => 'decimal:2',
        'pay_rate' => 'decimal:2',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function payrollProvider(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'payroll_provider_id');
    }

    public function paySummaries(): HasMany
    {
        return $this->hasMany(PayPeriodEmployeeSummary::class);
    }

    public function roundGroup(): BelongsTo
    {
        return $this->belongsTo(RoundGroup::class, 'round_group_id');
    }

    public function shiftSchedule(): BelongsTo
    {
        return $this->belongsTo(ShiftSchedule::class, 'shift_schedule_id');
    }

    /**
     * Get the shift through the shift schedule (source of truth)
     */
    public function shift(): HasOneThrough
    {
        return $this->hasOneThrough(Shift::class, ShiftSchedule::class, 'id', 'id', 'shift_schedule_id', 'shift_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    public function clockEvents(): HasMany
    {
        return $this->hasMany(ClockEvent::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get departments where this employee is the manager.
     */
    public function managedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'manager_id');
    }

    /**
     * Get vacation requests for this employee
     */
    public function vacationRequests(): HasMany
    {
        return $this->hasMany(VacationRequest::class);
    }

    /**
     * Get the vacation transactions for this employee
     */
    public function vacationTransactions(): HasMany
    {
        return $this->hasMany(VacationTransaction::class);
    }

    /**
     * Get the vacation policy assignments for this employee
     */
    public function vacationAssignments(): HasMany
    {
        return $this->hasMany(EmployeeVacationAssignment::class);
    }

    /**
     * Get the current active vacation policy assignment
     */
    public function currentVacationAssignment(): HasOne
    {
        return $this->hasOne(EmployeeVacationAssignment::class)
            ->where('is_active', true)
            ->where('effective_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->latest('effective_date');
    }

    /**
     * Get the current vacation policy through assignment
     */
    public function currentVacationPolicy(): HasOneThrough
    {
        return $this->hasOneThrough(
            VacationPolicy::class,
            EmployeeVacationAssignment::class,
            'employee_id', // Foreign key on employee_vacation_assignments table
            'id',          // Foreign key on vacation_policies table
            'id',          // Local key on employees table
            'vacation_policy_id' // Local key on employee_vacation_assignments table
        )->where('employee_vacation_assignments.is_active', true)
            ->where('employee_vacation_assignments.effective_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('employee_vacation_assignments.end_date')
                    ->orWhere('employee_vacation_assignments.end_date', '>=', now());
            });
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
