<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property string $rule_name Name of the overtime rule
 * @property int $hours_threshold Hours threshold for overtime calculation
 * @property float $multiplier Overtime pay multiplier
 * @property int|null $shift_id Foreign key to Shifts
 * @property int|null $consecutive_days_threshold Number of consecutive days required to trigger this rule
 * @property bool $applies_on_weekends Whether this rule applies on weekends
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Shift|null $shift
 * @property-read User|null $updater
 * @method static Builder<static>|OvertimeRule newModelQuery()
 * @method static Builder<static>|OvertimeRule newQuery()
 * @method static Builder<static>|OvertimeRule query()
 * @method static Builder<static>|OvertimeRule whereAppliesOnWeekends($value)
 * @method static Builder<static>|OvertimeRule whereConsecutiveDaysThreshold($value)
 * @method static Builder<static>|OvertimeRule whereCreatedAt($value)
 * @method static Builder<static>|OvertimeRule whereCreatedBy($value)
 * @method static Builder<static>|OvertimeRule whereHoursThreshold($value)
 * @method static Builder<static>|OvertimeRule whereId($value)
 * @method static Builder<static>|OvertimeRule whereMultiplier($value)
 * @method static Builder<static>|OvertimeRule whereRuleName($value)
 * @method static Builder<static>|OvertimeRule whereShiftId($value)
 * @method static Builder<static>|OvertimeRule whereUpdatedAt($value)
 * @method static Builder<static>|OvertimeRule whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class OvertimeRule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rule_name',
        'hours_threshold',
        'multiplier',
        'shift_id',
        'consecutive_days_threshold',
        'applies_on_weekends',
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
    ];

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
