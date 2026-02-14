<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * VacationCalendar - Stores employee vacation/PTO entries ONLY.
 * Holidays are now managed separately via HolidayInstance table.
 *
 * @property int $id
 * @property int $employee_id Foreign key to Employees
 * @property Carbon $vacation_date Date of the vacation
 * @property bool $is_half_day Indicates if the vacation is a half-day
 * @property bool $is_active Indicates if the vacation record is active
 * @property bool $is_recorded Indicates if this vacation has been recorded in the Attendance table
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Employee $employee
 * @property-read User|null $updater
 *
 * @method static Builder<static>|VacationCalendar newModelQuery()
 * @method static Builder<static>|VacationCalendar newQuery()
 * @method static Builder<static>|VacationCalendar query()
 * @method static Builder<static>|VacationCalendar whereCreatedAt($value)
 * @method static Builder<static>|VacationCalendar whereCreatedBy($value)
 * @method static Builder<static>|VacationCalendar whereEmployeeId($value)
 * @method static Builder<static>|VacationCalendar whereId($value)
 * @method static Builder<static>|VacationCalendar whereIsActive($value)
 * @method static Builder<static>|VacationCalendar whereIsHalfDay($value)
 * @method static Builder<static>|VacationCalendar whereUpdatedAt($value)
 * @method static Builder<static>|VacationCalendar whereUpdatedBy($value)
 * @method static Builder<static>|VacationCalendar whereVacationDate($value)
 * @method static Builder<static>|VacationCalendar whereIsRecorded($value)
 *
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
