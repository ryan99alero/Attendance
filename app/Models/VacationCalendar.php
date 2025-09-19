<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $employee_id Foreign key to Employees
 * @property \Illuminate\Support\Carbon $vacation_date Date of the vacation
 * @property bool $is_half_day Indicates if the vacation is a half-day
 * @property bool $is_active Indicates if the vacation record is active
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Employee $employee
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar whereIsHalfDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar whereVacationDate($value)
 * @property int $is_recorded Indicates if this vacation has been recorded in the Attendance table
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationCalendar whereIsRecorded($value)
 * @mixin \Eloquent
 */
class VacationCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'vacation_date',
        'is_half_day',
        'is_active',
        'is_recorded',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'vacation_date' => 'date',
        'is_half_day' => 'boolean',
        'is_active' => 'boolean',
        'is_recorded' => 'boolean',
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

    public function holidayTemplate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HolidayTemplate::class, 'holiday_template_id');
    }

    /**
     * Scope for manual vacation entries (not auto-managed)
     */
    public function scopeManual($query)
    {
        return $query->where('auto_managed', false);
    }

    /**
     * Scope for auto-managed vacation entries (holidays)
     */
    public function scopeAutoManaged($query)
    {
        return $query->where('auto_managed', true);
    }
}
