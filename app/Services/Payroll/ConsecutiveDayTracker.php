<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Tracks which days an employee worked and calculates consecutive day counts.
 * Used for applying consecutive day overtime rules (e.g., 7th day = double-time).
 */
class ConsecutiveDayTracker
{
    /**
     * @var array<string, float> Map of date strings to hours worked
     */
    protected array $workedDays = [];

    /**
     * @var float Minimum hours to count as "worked" for consecutive day purposes
     */
    protected float $minimumHoursForWorkDay = 0.01;

    /**
     * Create a new tracker from an array of daily hours.
     *
     * @param  array<string, float>  $dailyHours  Map of 'YYYY-MM-DD' => hours
     */
    public function __construct(array $dailyHours = [])
    {
        foreach ($dailyHours as $date => $hours) {
            if ($hours >= $this->minimumHoursForWorkDay) {
                $this->workedDays[$date] = (float) $hours;
            }
        }
    }

    /**
     * Create a tracker from a Collection of attendance records.
     */
    public static function fromAttendance(Collection $attendanceRecords, string $dateField = 'shift_date'): self
    {
        $dailyHours = [];

        foreach ($attendanceRecords as $record) {
            $date = $record->{$dateField};
            if ($date instanceof Carbon) {
                $date = $date->toDateString();
            }

            if (! isset($dailyHours[$date])) {
                $dailyHours[$date] = 0;
            }

            // Assuming records have an hours field or can be calculated
            $dailyHours[$date] += $record->hours ?? 0;
        }

        return new self($dailyHours);
    }

    /**
     * Set the minimum hours required to count as a "worked" day.
     */
    public function setMinimumHours(float $hours): self
    {
        $this->minimumHoursForWorkDay = $hours;

        return $this;
    }

    /**
     * Add a work day to the tracker.
     */
    public function addWorkDay(string|Carbon $date, float $hours): self
    {
        if ($date instanceof Carbon) {
            $date = $date->toDateString();
        }

        if ($hours >= $this->minimumHoursForWorkDay) {
            $this->workedDays[$date] = $hours;
        }

        return $this;
    }

    /**
     * Check if the employee worked on a specific date.
     */
    public function workedOn(Carbon|string $date): bool
    {
        if ($date instanceof Carbon) {
            $date = $date->toDateString();
        }

        return isset($this->workedDays[$date]) && $this->workedDays[$date] >= $this->minimumHoursForWorkDay;
    }

    /**
     * Get hours worked on a specific date.
     */
    public function getHours(Carbon|string $date): float
    {
        if ($date instanceof Carbon) {
            $date = $date->toDateString();
        }

        return $this->workedDays[$date] ?? 0.0;
    }

    /**
     * Get the number of consecutive days worked up to and including a given date.
     * Counts backwards from the given date.
     *
     * @param  Carbon|string  $date  The date to count up to (inclusive)
     * @return int Number of consecutive days worked
     */
    public function getConsecutiveDaysUpTo(Carbon|string $date): int
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        // If the target date wasn't worked, return 0
        if (! $this->workedOn($date)) {
            return 0;
        }

        $count = 1;
        $checkDate = $date->copy()->subDay();

        while ($this->workedOn($checkDate)) {
            $count++;
            $checkDate->subDay();
        }

        return $count;
    }

    /**
     * Get the sequence of consecutive worked dates ending on a given date.
     *
     * @param  Carbon|string  $date  The date to count up to (inclusive)
     * @return array<string> Array of date strings in the consecutive sequence
     */
    public function getConsecutiveSequence(Carbon|string $date): array
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        if (! $this->workedOn($date)) {
            return [];
        }

        $sequence = [$date->toDateString()];
        $checkDate = $date->copy()->subDay();

        while ($this->workedOn($checkDate)) {
            array_unshift($sequence, $checkDate->toDateString());
            $checkDate->subDay();
        }

        return $sequence;
    }

    /**
     * Check if a date is the Nth consecutive day worked.
     *
     * @param  Carbon|string  $date  The date to check
     * @param  int  $n  The number of consecutive days to check for
     * @return bool True if this is exactly the Nth consecutive day
     */
    public function isNthConsecutiveDay(Carbon|string $date, int $n): bool
    {
        return $this->getConsecutiveDaysUpTo($date) === $n;
    }

    /**
     * Check if a date meets or exceeds a consecutive day threshold.
     *
     * @param  Carbon|string  $date  The date to check
     * @param  int  $threshold  The minimum number of consecutive days
     * @return bool True if consecutive days >= threshold
     */
    public function meetsConsecutiveDayThreshold(Carbon|string $date, int $threshold): bool
    {
        return $this->getConsecutiveDaysUpTo($date) >= $threshold;
    }

    /**
     * Get all worked dates as an array.
     *
     * @return array<string>
     */
    public function getWorkedDates(): array
    {
        return array_keys($this->workedDays);
    }

    /**
     * Get all worked days with their hours.
     *
     * @return array<string, float>
     */
    public function getWorkedDaysWithHours(): array
    {
        return $this->workedDays;
    }

    /**
     * Get the total days worked in the tracking period.
     */
    public function getTotalDaysWorked(): int
    {
        return count($this->workedDays);
    }

    /**
     * Get the total hours worked across all tracked days.
     */
    public function getTotalHoursWorked(): float
    {
        return array_sum($this->workedDays);
    }

    /**
     * Check if the prior day was worked (for prior-day verification rules).
     *
     * @param  Carbon|string  $date  The date to check the prior day of
     * @return bool True if the day before was worked
     */
    public function priorDayWorked(Carbon|string $date): bool
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $this->workedOn($date->copy()->subDay());
    }

    /**
     * Get the longest consecutive streak within the tracked period.
     *
     * @return array{length: int, start: string|null, end: string|null}
     */
    public function getLongestStreak(): array
    {
        if (empty($this->workedDays)) {
            return ['length' => 0, 'start' => null, 'end' => null];
        }

        $dates = array_keys($this->workedDays);
        sort($dates);

        $longestLength = 1;
        $longestStart = $dates[0];
        $longestEnd = $dates[0];

        $currentLength = 1;
        $currentStart = $dates[0];

        for ($i = 1; $i < count($dates); $i++) {
            $prevDate = Carbon::parse($dates[$i - 1]);
            $currDate = Carbon::parse($dates[$i]);

            if ($prevDate->diffInDays($currDate) === 1) {
                $currentLength++;
            } else {
                if ($currentLength > $longestLength) {
                    $longestLength = $currentLength;
                    $longestStart = $currentStart;
                    $longestEnd = $dates[$i - 1];
                }
                $currentLength = 1;
                $currentStart = $dates[$i];
            }
        }

        // Check the last streak
        if ($currentLength > $longestLength) {
            $longestLength = $currentLength;
            $longestStart = $currentStart;
            $longestEnd = end($dates);
        }

        return [
            'length' => $longestLength,
            'start' => $longestStart,
            'end' => $longestEnd,
        ];
    }
}
