<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property string $frequency_name Name of the payroll frequency
 * @property int|null $weekly_day Day of the week for weekly payroll (0-6, Sun-Sat)
 * @property int|null $semimonthly_first_day First fixed day of the month for semimonthly payroll
 * @property int|null $semimonthly_second_day Second fixed day of the month for semimonthly payroll
 * @property int|null $monthly_day Day of the month for monthly payroll
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read string|null $monthly_description
 * @property-read string|null $semimonthly_description
 * @property-read string|null $weekly_day_name
 * @property-read User|null $updater
 * @method static Builder<static>|PayrollFrequency newModelQuery()
 * @method static Builder<static>|PayrollFrequency newQuery()
 * @method static Builder<static>|PayrollFrequency query()
 * @method static Builder<static>|PayrollFrequency whereCreatedAt($value)
 * @method static Builder<static>|PayrollFrequency whereCreatedBy($value)
 * @method static Builder<static>|PayrollFrequency whereFrequencyName($value)
 * @method static Builder<static>|PayrollFrequency whereId($value)
 * @method static Builder<static>|PayrollFrequency whereMonthlyDay($value)
 * @method static Builder<static>|PayrollFrequency whereSemimonthlyFirstDay($value)
 * @method static Builder<static>|PayrollFrequency whereSemimonthlySecondDay($value)
 * @method static Builder<static>|PayrollFrequency whereUpdatedAt($value)
 * @method static Builder<static>|PayrollFrequency whereUpdatedBy($value)
 * @method static Builder<static>|PayrollFrequency whereWeeklyDay($value)
 * @mixin \Eloquent
 */
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
     * Helpers: Description for Weekly Frequency.
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
     * Helpers: Description for Semimonthly Frequency.
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
     * Helpers: Description for Monthly Frequency.
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
