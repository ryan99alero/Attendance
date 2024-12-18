<?php

namespace App\Models;

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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Shift|null $shift
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule whereAppliesOnWeekends($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule whereConsecutiveDaysThreshold($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule whereHoursThreshold($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule whereMultiplier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule whereRuleName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRule whereUpdatedBy($value)
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
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the overtime rule.
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the shift associated with the overtime rule.
     */
    public function shift(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
