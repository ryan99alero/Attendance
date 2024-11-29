<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollFrequency extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'frequency_name',
        'weekly_day',
        'semimonthly_first_day',
        'semimonthly_second_day',
        'monthly_day',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'weekly_day' => 'integer',
        'semimonthly_first_day' => 'integer',
        'semimonthly_second_day' => 'integer',
        'monthly_day' => 'integer',
    ];

    /**
     * Relationship: Creator.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Updater.
     *
     * @return BelongsTo
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Helper: Description for Weekly Frequency.
     *
     * @return string|null
     */
    public function getWeeklyDayNameAttribute(): ?string
    {
        if ($this->weekly_day !== null) {
            return [
                0 => 'Sunday',
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
            ][$this->weekly_day] ?? null;
        }

        return null;
    }

    /**
     * Helper: Description for Semimonthly Frequency.
     *
     * @return string|null
     */
    public function getSemimonthlyDescriptionAttribute(): ?string
    {
        if ($this->semimonthly_first_day !== null && $this->semimonthly_second_day !== null) {
            return "1st Day: {$this->semimonthly_first_day}, 2nd Day: {$this->semimonthly_second_day}";
        }

        return null;
    }

    /**
     * Helper: Description for Monthly Frequency.
     *
     * @return string|null
     */
    public function getMonthlyDescriptionAttribute(): ?string
    {
        if ($this->monthly_day !== null) {
            return "Day: {$this->monthly_day}";
        }

        return null;
    }
}
