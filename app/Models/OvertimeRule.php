<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $rule_name Name of the overtime rule
 * @property string $rule_type Type of overtime rule
 * @property int $hours_threshold Hours threshold for overtime calculation
 * @property float $multiplier Overtime pay multiplier
 * @property int|null $shift_id Foreign key to Shifts
 * @property int|null $consecutive_days_threshold Number of consecutive days required to trigger this rule
 * @property bool $applies_on_weekends Whether this rule applies on weekends
 * @property array|null $applies_to_days Days of week this rule applies to
 * @property array|null $eligible_pay_types Pay types eligible for this rule
 * @property bool $requires_prior_day_worked Whether prior day must be worked
 * @property bool $only_applies_to_final_day Only applies on the threshold day
 * @property float $double_time_multiplier Double-time multiplier
 * @property int $priority Rule priority (lower = higher priority)
 * @property bool $is_active Whether the rule is active
 * @property string|null $description Rule description
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Shift|null $shift
 * @property-read User|null $updater
 *
 * @method static Builder<static>|OvertimeRule active()
 * @method static Builder<static>|OvertimeRule forShift(?int $shiftId)
 * @method static Builder<static>|OvertimeRule forPayType(string $payType)
 * @method static Builder<static>|OvertimeRule byPriority()
 * @method static Builder<static>|OvertimeRule ofType(string $type)
 *
 * @mixin \Eloquent
 */
class OvertimeRule extends Model
{
    use HasFactory;

    // Rule type constants
    public const TYPE_WEEKLY_THRESHOLD = 'weekly_threshold';

    public const TYPE_DAILY_THRESHOLD = 'daily_threshold';

    public const TYPE_WEEKEND_DAY = 'weekend_day';

    public const TYPE_CONSECUTIVE_DAY = 'consecutive_day';

    public const TYPE_HOLIDAY = 'holiday';

    // Pay type constants
    public const PAY_TYPE_HOURLY = 'hourly';

    public const PAY_TYPE_SALARY = 'salary';

    public const PAY_TYPE_CONTRACT = 'contract';

    // Day of week constants (matching PHP's Carbon/DateTime)
    public const SUNDAY = 0;

    public const MONDAY = 1;

    public const TUESDAY = 2;

    public const WEDNESDAY = 3;

    public const THURSDAY = 4;

    public const FRIDAY = 5;

    public const SATURDAY = 6;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rule_name',
        'rule_type',
        'hours_threshold',
        'multiplier',
        'shift_id',
        'consecutive_days_threshold',
        'applies_on_weekends',
        'applies_to_days',
        'eligible_pay_types',
        'requires_prior_day_worked',
        'only_applies_to_final_day',
        'double_time_multiplier',
        'priority',
        'is_active',
        'description',
        'created_by',
        'updated_by',
    ];

    /**
     * Cast attributes to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'hours_threshold' => 'integer',
        'multiplier' => 'float',
        'consecutive_days_threshold' => 'integer',
        'applies_on_weekends' => 'boolean',
        'applies_to_days' => 'array',
        'eligible_pay_types' => 'array',
        'requires_prior_day_worked' => 'boolean',
        'only_applies_to_final_day' => 'boolean',
        'double_time_multiplier' => 'float',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get all available rule types.
     */
    public static function getRuleTypes(): array
    {
        return [
            self::TYPE_WEEKLY_THRESHOLD => 'Weekly Hours Threshold',
            self::TYPE_DAILY_THRESHOLD => 'Daily Hours Threshold',
            self::TYPE_WEEKEND_DAY => 'Weekend/Specific Day',
            self::TYPE_CONSECUTIVE_DAY => 'Consecutive Days',
            self::TYPE_HOLIDAY => 'Holiday',
        ];
    }

    /**
     * Get all available pay types.
     */
    public static function getPayTypes(): array
    {
        return [
            self::PAY_TYPE_HOURLY => 'Hourly',
            self::PAY_TYPE_SALARY => 'Salary',
            self::PAY_TYPE_CONTRACT => 'Contract',
        ];
    }

    /**
     * Get all days of week.
     */
    public static function getDaysOfWeek(): array
    {
        return [
            self::SUNDAY => 'Sunday',
            self::MONDAY => 'Monday',
            self::TUESDAY => 'Tuesday',
            self::WEDNESDAY => 'Wednesday',
            self::THURSDAY => 'Thursday',
            self::FRIDAY => 'Friday',
            self::SATURDAY => 'Saturday',
        ];
    }

    /**
     * Scope to only active rules.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by shift.
     */
    public function scopeForShift(Builder $query, ?int $shiftId): Builder
    {
        if ($shiftId === null) {
            return $query->whereNull('shift_id');
        }

        return $query->where(function ($q) use ($shiftId) {
            $q->where('shift_id', $shiftId)
                ->orWhereNull('shift_id'); // Also include global rules
        });
    }

    /**
     * Scope to filter by pay type.
     */
    public function scopeForPayType(Builder $query, string $payType): Builder
    {
        return $query->where(function ($q) use ($payType) {
            $q->whereNull('eligible_pay_types')
                ->orWhereJsonContains('eligible_pay_types', $payType);
        });
    }

    /**
     * Scope to order by priority.
     */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority', 'asc');
    }

    /**
     * Scope to filter by rule type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('rule_type', $type);
    }

    /**
     * Check if this rule applies to a specific day of week.
     *
     * @param  int  $dayOfWeek  Day number (0 = Sunday, 6 = Saturday)
     */
    public function appliesToDay(int $dayOfWeek): bool
    {
        // If no specific days are set, rule applies to all days
        if (empty($this->applies_to_days)) {
            return true;
        }

        return in_array($dayOfWeek, $this->applies_to_days, true);
    }

    /**
     * Check if this rule applies to a specific pay type.
     */
    public function appliesToPayType(?string $payType): bool
    {
        // If no pay types specified, rule applies to all pay types
        if (empty($this->eligible_pay_types)) {
            return true;
        }

        // Salary employees typically never get overtime
        if ($payType === self::PAY_TYPE_SALARY) {
            return in_array(self::PAY_TYPE_SALARY, $this->eligible_pay_types, true);
        }

        return in_array($payType, $this->eligible_pay_types, true);
    }

    /**
     * Check if this is a consecutive day rule.
     */
    public function isConsecutiveDayRule(): bool
    {
        return $this->rule_type === self::TYPE_CONSECUTIVE_DAY;
    }

    /**
     * Check if this is a weekly threshold rule.
     */
    public function isWeeklyThresholdRule(): bool
    {
        return $this->rule_type === self::TYPE_WEEKLY_THRESHOLD;
    }

    /**
     * Check if this is a daily threshold rule.
     */
    public function isDailyThresholdRule(): bool
    {
        return $this->rule_type === self::TYPE_DAILY_THRESHOLD;
    }

    /**
     * Check if this is a weekend/specific day rule.
     */
    public function isWeekendDayRule(): bool
    {
        return $this->rule_type === self::TYPE_WEEKEND_DAY;
    }

    /**
     * Check if this is a holiday rule.
     */
    public function isHolidayRule(): bool
    {
        return $this->rule_type === self::TYPE_HOLIDAY;
    }

    /**
     * Get the effective multiplier based on whether this should be double-time.
     */
    public function getEffectiveMultiplier(bool $isDoubleTime = false): float
    {
        return $isDoubleTime ? $this->double_time_multiplier : $this->multiplier;
    }

    /**
     * Get the user who created the overtime rule.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the overtime rule.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the shift associated with the overtime rule.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
