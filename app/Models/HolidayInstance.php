<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $holiday_template_id
 * @property Carbon $holiday_date
 * @property int $year
 * @property string $name
 * @property float $holiday_multiplier
 * @property float $standard_hours
 * @property bool $require_day_before
 * @property bool $require_day_after
 * @property bool $paid_if_not_worked
 * @property array|null $eligible_pay_types
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read HolidayTemplate $template
 *
 * @method static Builder<static>|HolidayInstance active()
 * @method static Builder<static>|HolidayInstance forYear(int $year)
 * @method static Builder<static>|HolidayInstance forDate(Carbon $date)
 * @method static Builder<static>|HolidayInstance forDateRange(Carbon $startDate, Carbon $endDate)
 *
 * @mixin \Eloquent
 */
class HolidayInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'holiday_template_id',
        'holiday_date',
        'year',
        'name',
        'holiday_multiplier',
        'standard_hours',
        'require_day_before',
        'require_day_after',
        'paid_if_not_worked',
        'eligible_pay_types',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'year' => 'integer',
        'holiday_multiplier' => 'float',
        'standard_hours' => 'float',
        'require_day_before' => 'boolean',
        'require_day_after' => 'boolean',
        'paid_if_not_worked' => 'boolean',
        'eligible_pay_types' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the template this instance was generated from.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(HolidayTemplate::class, 'holiday_template_id');
    }

    /**
     * Scope to only active instances.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by year.
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to find holiday for a specific date.
     */
    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('holiday_date', $date->toDateString());
    }

    /**
     * Scope to find holidays within a date range.
     */
    public function scopeForDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('holiday_date', [
            $startDate->toDateString(),
            $endDate->toDateString(),
        ]);
    }

    /**
     * Check if an employee qualifies for this holiday based on pay type.
     */
    public function appliesToEmployee(Employee $employee): bool
    {
        // If no eligible pay types are specified, apply to all
        if (empty($this->eligible_pay_types)) {
            return true;
        }

        $employeePayType = $employee->pay_type ?? 'hourly';

        // Handle full-time vs part-time hourly
        if ($employeePayType === 'hourly') {
            $isFullTime = $employee->full_time ?? false;
            $effectiveType = $isFullTime ? 'hourly_fulltime' : 'hourly_parttime';

            // Check both the specific type and generic 'hourly'
            return in_array($effectiveType, $this->eligible_pay_types, true)
                || in_array('hourly', $this->eligible_pay_types, true);
        }

        return in_array($employeePayType, $this->eligible_pay_types, true);
    }

    /**
     * Check if the day before requirement is met.
     */
    public function dayBeforeWorked(array $workedDates): bool
    {
        if (! $this->require_day_before) {
            return true;
        }

        $dayBefore = $this->holiday_date->copy()->subDay()->toDateString();

        return in_array($dayBefore, $workedDates, true);
    }

    /**
     * Check if the day after requirement is met.
     */
    public function dayAfterWorked(array $workedDates): bool
    {
        if (! $this->require_day_after) {
            return true;
        }

        $dayAfter = $this->holiday_date->copy()->addDay()->toDateString();

        return in_array($dayAfter, $workedDates, true);
    }

    /**
     * Create holiday instances for a given year from all active templates.
     */
    public static function generateForYear(int $year): int
    {
        $templates = HolidayTemplate::active()->get();
        $created = 0;

        foreach ($templates as $template) {
            $instance = self::createFromTemplate($template, $year);
            if ($instance) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Create a holiday instance from a template for a specific year.
     */
    public static function createFromTemplate(HolidayTemplate $template, int $year): ?self
    {
        // Check if instance already exists
        $existing = self::where('holiday_template_id', $template->id)
            ->where('year', $year)
            ->first();

        if ($existing) {
            return null; // Already exists
        }

        try {
            $holidayDate = $template->calculateDateForYear($year);

            return self::create([
                'holiday_template_id' => $template->id,
                'holiday_date' => $holidayDate,
                'year' => $year,
                'name' => $template->name,
                'holiday_multiplier' => $template->holiday_multiplier ?? 2.00,
                'standard_hours' => $template->standard_holiday_hours ?? 8.00,
                'require_day_before' => $template->require_day_before ?? false,
                'require_day_after' => $template->require_day_after ?? false,
                'paid_if_not_worked' => $template->paid_if_not_worked ?? true,
                'eligible_pay_types' => $template->eligible_pay_types,
                'is_active' => $template->is_active,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to create holiday instance for template {$template->id} year {$year}: ".$e->getMessage());

            return null;
        }
    }
}
