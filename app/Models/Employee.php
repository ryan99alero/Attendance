<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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
 * @property \Illuminate\Support\Carbon|null $termination_date
 * @property bool $is_active
 * @property bool $full_time
 * @property bool $vacation_pay
 * @property-read string $full_name
 * @property-read \App\Models\Department|null $department
 * @property-read \App\Models\RoundingRule|null $roundingRule
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\ShiftSchedule|null $schedule
 * @property string $external_department_id
 * @property string|null $email Employee email address
 * @property int|null $shift_id Foreign key referencing the shifts table
 * @property string|null $photograph Path or URL of the employee photograph
 * @property int|null $created_by Foreign key referencing the user who created the record
 * @property int|null $updated_by Foreign key referencing the user who last updated the record
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $full_names Concatenated full name of the employee
 * @property int|null $shift_schedule_id Foreign key referencing the shift schedules table
 * @property-read \App\Models\RoundGroup|null $roundGroup
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereExternalDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereFullNames($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereFullTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee wherePhotograph($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereRoundGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereShiftScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereTerminationDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereVacationPay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereZip($value)
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
