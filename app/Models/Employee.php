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
        'payroll_frequency_id',
        'normal_hrs_per_day',
        'paid_lunch',
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
    public function getLinkedUserIdAttribute()
    {
        // Return the ID of the associated user
        return $this->user?->id;
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
     * Relationship: Payroll Frequency.
     *
     * @return BelongsTo
     */
    public function payrollFrequency(): BelongsTo
    {
        return $this->belongsTo(PayrollFrequency::class, 'payroll_frequency_id');
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
    protected static function boot()
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
