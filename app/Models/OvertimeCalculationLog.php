<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $pay_period_id
 * @property Carbon $work_date
 * @property float $total_hours_worked
 * @property float $regular_hours
 * @property float $overtime_hours
 * @property float $double_time_hours
 * @property float $holiday_hours
 * @property int|null $overtime_rule_id
 * @property int|null $holiday_instance_id
 * @property string|null $calculation_reason
 * @property array|null $calculation_context
 * @property float|null $overtime_multiplier
 * @property float|null $double_time_multiplier
 * @property bool $is_finalized
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read PayPeriod $payPeriod
 * @property-read OvertimeRule|null $overtimeRule
 * @property-read HolidayInstance|null $holidayInstance
 * @property-read User|null $creator
 *
 * @method static Builder<static>|OvertimeCalculationLog forEmployee(int $employeeId)
 * @method static Builder<static>|OvertimeCalculationLog forPayPeriod(int $payPeriodId)
 * @method static Builder<static>|OvertimeCalculationLog forDate(Carbon $date)
 * @method static Builder<static>|OvertimeCalculationLog finalized()
 * @method static Builder<static>|OvertimeCalculationLog pending()
 *
 * @mixin \Eloquent
 */
class OvertimeCalculationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'pay_period_id',
        'work_date',
        'total_hours_worked',
        'regular_hours',
        'overtime_hours',
        'double_time_hours',
        'holiday_hours',
        'overtime_rule_id',
        'holiday_instance_id',
        'calculation_reason',
        'calculation_context',
        'overtime_multiplier',
        'double_time_multiplier',
        'is_finalized',
        'created_by',
    ];

    protected $casts = [
        'work_date' => 'date',
        'total_hours_worked' => 'float',
        'regular_hours' => 'float',
        'overtime_hours' => 'float',
        'double_time_hours' => 'float',
        'holiday_hours' => 'float',
        'calculation_context' => 'array',
        'overtime_multiplier' => 'float',
        'double_time_multiplier' => 'float',
        'is_finalized' => 'boolean',
    ];

    /**
     * Get the employee this calculation is for.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the pay period this calculation belongs to.
     */
    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    /**
     * Get the overtime rule that was applied.
     */
    public function overtimeRule(): BelongsTo
    {
        return $this->belongsTo(OvertimeRule::class);
    }

    /**
     * Get the holiday instance if this was a holiday.
     */
    public function holidayInstance(): BelongsTo
    {
        return $this->belongsTo(HolidayInstance::class);
    }

    /**
     * Get the user who created this calculation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by pay period.
     */
    public function scopeForPayPeriod(Builder $query, int $payPeriodId): Builder
    {
        return $query->where('pay_period_id', $payPeriodId);
    }

    /**
     * Scope to filter by work date.
     */
    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('work_date', $date->toDateString());
    }

    /**
     * Scope for finalized calculations.
     */
    public function scopeFinalized(Builder $query): Builder
    {
        return $query->where('is_finalized', true);
    }

    /**
     * Scope for pending (not finalized) calculations.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('is_finalized', false);
    }

    /**
     * Get the total paid hours (regular + OT equivalent + DT equivalent).
     */
    public function getTotalPaidHoursAttribute(): float
    {
        $otMultiplier = $this->overtime_multiplier ?? 1.5;
        $dtMultiplier = $this->double_time_multiplier ?? 2.0;

        return $this->regular_hours
            + ($this->overtime_hours * $otMultiplier)
            + ($this->double_time_hours * $dtMultiplier)
            + ($this->holiday_hours * $dtMultiplier);
    }

    /**
     * Get a summary string of this calculation.
     */
    public function getSummaryAttribute(): string
    {
        $parts = [];

        if ($this->regular_hours > 0) {
            $parts[] = "{$this->regular_hours}h regular";
        }
        if ($this->overtime_hours > 0) {
            $parts[] = "{$this->overtime_hours}h OT";
        }
        if ($this->double_time_hours > 0) {
            $parts[] = "{$this->double_time_hours}h DT";
        }
        if ($this->holiday_hours > 0) {
            $parts[] = "{$this->holiday_hours}h holiday";
        }

        return implode(', ', $parts) ?: 'No hours';
    }

    /**
     * Create or update a log entry for a specific employee/pay period/date.
     */
    public static function logCalculation(
        int $employeeId,
        int $payPeriodId,
        Carbon $workDate,
        array $data
    ): self {
        return self::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'pay_period_id' => $payPeriodId,
                'work_date' => $workDate->toDateString(),
            ],
            array_merge($data, [
                'created_by' => auth()->id(),
            ])
        );
    }

    /**
     * Get the sum of all hours for an employee in a pay period.
     */
    public static function sumForPayPeriod(int $employeeId, int $payPeriodId): array
    {
        $result = self::where('employee_id', $employeeId)
            ->where('pay_period_id', $payPeriodId)
            ->selectRaw('
                SUM(total_hours_worked) as total_worked,
                SUM(regular_hours) as total_regular,
                SUM(overtime_hours) as total_overtime,
                SUM(double_time_hours) as total_double_time,
                SUM(holiday_hours) as total_holiday
            ')
            ->first();

        return [
            'total_worked' => (float) ($result->total_worked ?? 0),
            'regular' => (float) ($result->total_regular ?? 0),
            'overtime' => (float) ($result->total_overtime ?? 0),
            'double_time' => (float) ($result->total_double_time ?? 0),
            'holiday' => (float) ($result->total_holiday ?? 0),
        ];
    }
}
