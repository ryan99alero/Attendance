<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class HolidayTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'calculation_rule',
        'auto_create_days_ahead',
        'applies_to_all_employees',
        'eligible_pay_types',
        'is_active',
        'description',
    ];

    protected $casts = [
        'calculation_rule' => 'json',
        'eligible_pay_types' => 'json',
        'applies_to_all_employees' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active holiday templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate the holiday date for a given year
     */
    public function calculateDateForYear(int $year): Carbon
    {
        $rule = $this->calculation_rule;

        switch ($this->type) {
            case 'fixed_date':
                // Fixed date like Christmas (Dec 25) or New Year's (Jan 1)
                return Carbon::create($year, $rule['month'], $rule['day']);

            case 'relative':
                // Relative dates like "4th Thursday in November" (Thanksgiving)
                return $this->calculateRelativeDate($year, $rule);

            case 'custom':
                // Custom calculation (e.g., Easter-based holidays)
                return $this->calculateCustomDate($year, $rule);

            default:
                throw new \InvalidArgumentException("Unknown holiday type: {$this->type}");
        }
    }

    /**
     * Calculate relative dates like "Last Monday in May"
     */
    private function calculateRelativeDate(int $year, array $rule): Carbon
    {
        $month = $rule['month'];
        $dayOfWeek = $rule['day_of_week']; // 0 = Sunday, 1 = Monday, etc.
        $occurrence = $rule['occurrence']; // 1 = first, 2 = second, -1 = last

        if ($occurrence > 0) {
            // Find the Nth occurrence (e.g., 4th Thursday)
            $firstOfMonth = Carbon::create($year, $month, 1);
            $firstDayOfWeek = $firstOfMonth->dayOfWeek;

            $daysToAdd = ($dayOfWeek - $firstDayOfWeek + 7) % 7;
            $targetDate = $firstOfMonth->addDays($daysToAdd + (($occurrence - 1) * 7));

            return $targetDate;
        } else {
            // Find the last occurrence (e.g., last Monday)
            $lastOfMonth = Carbon::create($year, $month)->endOfMonth();

            while ($lastOfMonth->dayOfWeek !== $dayOfWeek) {
                $lastOfMonth->subDay();
            }

            return $lastOfMonth;
        }
    }

    /**
     * Calculate custom dates (e.g., Easter-based)
     */
    private function calculateCustomDate(int $year, array $rule): Carbon
    {
        switch ($rule['base']) {
            case 'easter':
                $easter = Carbon::createFromTimestamp(easter_date($year));
                return $easter->addDays($rule['offset_days'] ?? 0);

            default:
                throw new \InvalidArgumentException("Unknown custom rule base: {$rule['base']}");
        }
    }

    /**
     * Get vacation calendars using this template
     */
    public function vacationCalendars()
    {
        return $this->hasMany(VacationCalendar::class);
    }
}