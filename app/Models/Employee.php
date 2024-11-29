<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'address',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'external_id',
        'department_id',
        'shift_id',
        'rounding_method',
        'normal_hrs_per_day',
        'paid_lunch',
        'payroll_frequency_id', // Updated to reference PayrollFrequency
        'photograph',
        'start_date',
        'start_time',
        'stop_time',
        'termination_date',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'normal_hrs_per_day' => 'float',
        'paid_lunch' => 'boolean',
        'start_date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'stop_time' => 'datetime:H:i:s',
        'termination_date' => 'date',
        'is_active' => 'boolean',
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
     * Relationship: Shift.
     *
     * @return BelongsTo
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Relationship: Rounding Method.
     *
     * @return BelongsTo
     */
    public function roundingMethod(): BelongsTo
    {
        return $this->belongsTo(RoundingRule::class, 'rounding_method');
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
     * Relationship: Cards.
     *
     * @return HasMany
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'employee_id');
    }

    /**
     * Relationship: Vacation Balance.
     *
     * @return HasOne
     */
    public function vacationBalance(): HasOne
    {
        return $this->hasOne(VacationBalance::class, 'employee_id');
    }

    /**
     * Relationship: Vacation Calendars.
     *
     * @return HasMany
     */
    public function vacationCalendars(): HasMany
    {
        return $this->hasMany(VacationCalendar::class, 'employee_id');
    }

    /**
     * Relationship: Creator.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Updater.
     *
     * @return BelongsTo
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relationship: User (if exists).
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
}
