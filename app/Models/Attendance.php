<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Classification;
use Carbon\Carbon;

/**
 * 
 *
 * @property int $id
 * @property int|null $employee_id Foreign key to Employees
 * @property int|null $device_id Foreign key to Devices
 * @property string $punch_time
 * @property bool $is_manual Indicates if the attendance was manually recorded
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $issue_notes Notes or issues related to the attendance record
 * @property string $status
 * @property int|null $punch_type_id
 * @property bool|null $is_migrated
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Device|null $device
 * @property-read \App\Models\Employee|null $employee
 * @property-read \App\Models\PunchType|null $punchType
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance byStatus(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereIsManual($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereIsMigrated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereIssueNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance wherePunchTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance wherePunchTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance withinTimeRange(string $startTime, string $endTime)
 * @property string|null $employee_external_id External ID of the employee for mapping
 * @property int|null $classification_id Foreign key to classification table
 * @property int|null $holiday_id
 * @property string|null $external_group_id Links to attendance_time_groups.external_group_id
 * @property string|null $shift_date The assigned workday for this attendance record
 * @property int $is_archived Indicates if record is archived
 * @property-read Classification|null $classification
 * @property-read \App\Models\Employee|null $employeeByExternalId
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereClassificationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereEmployeeExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereExternalGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereHolidayId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereIsArchived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereShiftDate($value)
 * @mixin \Eloquent
 */
class Attendance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'employee_external_id',
        'device_id',
        'punch_time',
        'classification_id',
        'punch_type_id',
        'status',
        'issue_notes',
        'is_manual',
        'created_by',
        'updated_by',
        'holiday_id',
        'external_group_id',
        'shift_date',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'punch_time' => 'datetime', // Use raw datetime for flexibility
        'is_manual' => 'boolean',
        'is_migrated' => 'boolean',
    ];

    protected $guarded = ['is_migrated'];

    /**
     * Accessor for `punch_time` to format it as `Y-m-d H:i:s` for display purposes.
     */
    public function getPunchTimeAttribute($value): string
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }
    public function classification()
    {
        return $this->belongsTo(Classification::class);
    }
    /**
     * Mutator for `punch_time` to ensure it is saved in full datetime format.
     */
    /**
     * Mutator for `punch_time` to ensure it is saved in full datetime format.
     */
    /**
     * Mutator for `punch_time` to ensure it is saved in full datetime format.
     */
    public function setPunchTimeAttribute($value): void
    {
        try {
            // Only parse and set if $value is not null and is a valid date
            if (!empty($value)) {
                $this->attributes['punch_time'] = Carbon::parse($value)->format('Y-m-d H:i:s');
            } else {
                $this->attributes['punch_time'] = null; // Set null if $value is invalid
            }
        } catch (\Exception $e) {
            // Log the invalid value for debugging purposes
            Log::error("Invalid punch_time value: " . json_encode($value) . ". Error: " . $e->getMessage());
            $this->attributes['punch_time'] = null; // Gracefully handle the error
        }
    }

    /**
     * Relationship with the `PunchType` model.
     */
    public function punchType(): BelongsTo
    {
        return $this->belongsTo(PunchType::class, 'punch_type_id');
    }

    /**
     * Relationship with the `Employee` model.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
    /**
     * Relationship to map external ID to employee.
     */
    public function employeeByExternalId(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_external_id', 'external_id');
    }
    /**
     * Relationship with the `Device` model.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Relationship with the `User` model for the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with the `User` model for the updater.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to filter attendance records by status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter attendance records within a time range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startTime
     * @param string $endTime
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithinTimeRange($query, string $startTime, string $endTime)
    {
        return $query->whereBetween('punch_time', [$startTime, $endTime]);
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
    /**
     * Get status options for the 'status' field.
     *
     * @return array
     */
    public static function getStatusOptions(): array
    {
        $type = \DB::selectOne("SHOW COLUMNS FROM `attendances` WHERE Field = 'status'")->Type;

        preg_match('/^enum\((.*)\)$/', $type, $matches);
        $enumOptions = array_map(function ($value) {
            return trim($value, "'");
        }, explode(',', $matches[1]));

        return array_combine($enumOptions, $enumOptions);
    }
}
