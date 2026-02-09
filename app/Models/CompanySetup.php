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
        'payroll_frequency_id',
        'payroll_start_date',
        'vacation_accrual_method',
        'allow_carryover',
        'max_carryover_hours',
        'max_accrual_balance',
        'prorate_new_hires',
        // Calendar Year Method Fields
        'calendar_year_award_date',
        'calendar_year_prorate_partial',
        // Pay Period Method Fields
        'pay_period_hours_per_period',
        'pay_period_accrue_immediately',
        'pay_period_waiting_periods',
        // Anniversary Method Fields
        'anniversary_first_year_waiting_period',
        'anniversary_award_on_anniversary',
        'anniversary_max_days_cap',
        'anniversary_allow_partial_year',
        // Clock Event Processing Settings
        'clock_event_sync_frequency',
        'clock_event_batch_size',
        'clock_event_auto_retry_failed',
        'clock_event_daily_sync_time',
        // Device Management Settings
        'config_poll_interval_minutes',
        'firmware_check_interval_hours',
        'allow_device_poll_override',
        // Device Offline Alerting
        'device_alert_email',
        'device_offline_threshold_minutes',
        // SMTP Configuration
        'smtp_enabled',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_address',
        'smtp_from_name',
        'smtp_reply_to',
        'smtp_timeout',
        'smtp_verify_peer',
    ];

    protected $casts = [
        'auto_adjust_punches' => 'boolean',
        'use_ml_for_punch_matching' => 'boolean',
        'enforce_shift_schedules' => 'boolean',
        'allow_manual_time_edits' => 'boolean',
        'payroll_start_date' => 'date',
        'allow_carryover' => 'boolean',
        'max_carryover_hours' => 'decimal:2',
        'max_accrual_balance' => 'decimal:2',
        'prorate_new_hires' => 'boolean',
        // Calendar Year Method Casts
        'calendar_year_award_date' => 'date',
        'calendar_year_prorate_partial' => 'boolean',
        // Pay Period Method Casts
        'pay_period_hours_per_period' => 'decimal:4',
        'pay_period_accrue_immediately' => 'boolean',
        // Anniversary Method Casts
        'anniversary_first_year_waiting_period' => 'boolean',
        'anniversary_award_on_anniversary' => 'boolean',
        'anniversary_allow_partial_year' => 'boolean',
        // Clock Event Processing Casts
        'clock_event_auto_retry_failed' => 'boolean',
        'clock_event_daily_sync_time' => 'datetime:H:i:s',
        // Device Management Casts
        'allow_device_poll_override' => 'boolean',
        // SMTP Casts
        'smtp_enabled' => 'boolean',
        'smtp_password' => 'encrypted',
        'smtp_verify_peer' => 'boolean',
    ];

    /**
     * Get the payroll frequency for the company
     */
    public function payrollFrequency()
    {
        return $this->belongsTo(PayrollFrequency::class, 'payroll_frequency_id');
    }
}
