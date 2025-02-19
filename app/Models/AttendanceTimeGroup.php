<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id Primary key of the attendance_time_groups table
 * @property int $employee_id Foreign key to employees table
 * @property string $external_group_id Unique ID for this time group, format: employee_external_id + shift_date
 * @property string|null $shift_date The official workday this shift is assigned to
 * @property string|null $shift_window_start Start of the work period for this shift group
 * @property string|null $shift_window_end End of the work period for this shift group
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp of when the record was created
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp of when the record was last updated
 * @property int $is_archived Indicates if record is archived
 * @property string|null $lunch_start_time Expected lunch break start time
 * @property string|null $lunch_end_time Expected lunch break end time
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup whereExternalGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup whereIsArchived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup whereLunchEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup whereLunchStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup whereShiftDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup whereShiftWindowEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup whereShiftWindowStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceTimeGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AttendanceTimeGroup extends Model
{
    use HasFactory;

    protected $table = 'attendance_time_groups'; // Ensure it matches your table name

    protected $fillable = [
        'employee_id',
        'shift_date',
        'external_group_id',
        'created_by',
        'updated_by',
    ];

    public $timestamps = true; // Assumes you have created_at and updated_at
}
