<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Punch extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'device_id',
        'punch_type_id',
        'punch_time',
        'pay_period_id', // Ensure this is included
        'is_altered',
        'is_late',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'punch_time' => 'datetime',
        'is_altered' => 'boolean',
        'is_late' => 'boolean',
    ];

    /**
     * Automatically flag 'is_altered' when a record is updated.
     */
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($punch) {
            $dirtyFields = $punch->getDirty();

            // Ignore changes to 'is_altered'
            if (array_key_exists('is_altered', $dirtyFields)) {
                unset($dirtyFields['is_altered']);
            }

            // If any other field was changed, set 'is_altered' to true
            if (!empty($dirtyFields)) {
                $punch->is_altered = true;
            }
        });
    }

    /**
     * Relationship with Employee model.
     */
    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Relationship with Device model.
     */
    public function device(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * Relationship with PunchType model.
     */
    public function punchType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PunchType::class, 'punch_type_id');
    }

    /**
     * Relationship with User model for record creator.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with User model for record updater.
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relationship with PayPeriod model for Record Fetch.
     */
    public function payPeriod(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    /**
     * Scope for shifts crossing midnight, grouping punches within a 24-hour window.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $employeeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForShiftsCrossingMidnight($query, $employeeId)
    {
        // Get the employee's shift start time from the ShiftSchedule table
        $referenceTime = ShiftSchedule::where('employee_id', $employeeId)
            ->orWhere('department_id', function ($query) use ($employeeId) {
                $query->select('department_id')
                    ->from('employees')
                    ->where('id', $employeeId);
            })
            ->value('start_time') ?? '06:00:00'; // Default to 6:00 AM if no schedule found

        return $query->whereRaw("
        TIME(punch_time) >= ?
        OR TIME(punch_time) < ?
    ", [$referenceTime, $referenceTime])
            ->orderBy('employee_id')
            ->orderBy('punch_time');
    }
}
