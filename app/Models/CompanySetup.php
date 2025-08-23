<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int|null $attendance_flexibility_minutes Number of minutes allowed before/after a shift for attendance matching
 * @property string|null $logging_level Defines the level of logging in the system
 * @property int|null $auto_adjust_punches Whether to automatically adjust punch types for incomplete records
 * @property int|null $use_ml_for_punch_matching Enable ML-based punch classification
 * @property int|null $enforce_shift_schedules Require employees to adhere to assigned shift schedules
 * @property int|null $allow_manual_time_edits Allow admins to manually edit time records
 * @property int|null $max_shift_length Maximum shift length in hours before requiring admin approval
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|CompanySetup newModelQuery()
 * @method static Builder<static>|CompanySetup newQuery()
 * @method static Builder<static>|CompanySetup query()
 * @method static Builder<static>|CompanySetup whereAllowManualTimeEdits($value)
 * @method static Builder<static>|CompanySetup whereAttendanceFlexibilityMinutes($value)
 * @method static Builder<static>|CompanySetup whereAutoAdjustPunches($value)
 * @method static Builder<static>|CompanySetup whereCreatedAt($value)
 * @method static Builder<static>|CompanySetup whereEnforceShiftSchedules($value)
 * @method static Builder<static>|CompanySetup whereId($value)
 * @method static Builder<static>|CompanySetup whereLoggingLevel($value)
 * @method static Builder<static>|CompanySetup whereMaxShiftLength($value)
 * @method static Builder<static>|CompanySetup whereUpdatedAt($value)
 * @method static Builder<static>|CompanySetup whereUseMlForPunchMatching($value)
 * @mixin \Eloquent
 */
class CompanySetup extends Model
{
    use HasFactory;

    protected $table = 'company_setup';

    protected $fillable = [
        'attendance_flexibility_minutes',
        'logging_level',
        'debug_punch_assignment_mode',
        'auto_adjust_punches',
        'heuristic_min_punch_gap',
        'use_ml_for_punch_matching',
        'enforce_shift_schedules',
        'allow_manual_time_edits',
        'max_shift_length',
    ];
}
