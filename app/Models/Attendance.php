<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'device_id',
        'punch_time',
        'punch_type_id',
        'status',
        'issue_notes',
        'is_manual',
        'created_by',
        'updated_by',
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
     * Accessor for `punch_time` to format it as `Y-m-d H:i` for display purposes.
     */
    public function getPunchTimeAttribute($value): string
    {
        return Carbon::parse($value)->format('Y-m-d H:i');
    }

    /**
     * Mutator for `punch_time` to ensure it is saved in full datetime format.
     */
    public function setPunchTimeAttribute($value): void
    {
        $this->attributes['punch_time'] = Carbon::parse($value)->format('Y-m-d H:i:s');
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
}
