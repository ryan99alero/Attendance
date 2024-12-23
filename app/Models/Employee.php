<?php

namespace App\Models;

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
 * @property \Illuminate\Support\Carbon|null $termination_date
 * @property bool $is_active
 * @property bool $full_time
 * @property bool $vacation_pay
 * @property-read string $full_name
 * @property-read \App\Models\Department|null $department
 * @property-read \App\Models\RoundingRule|null $roundingRule
 * @property-read \App\Models\PayrollFrequency|null $payrollFrequency
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\ShiftSchedule|null $schedule
 */
class Employee extends Model
{
    use HasFactory;

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
