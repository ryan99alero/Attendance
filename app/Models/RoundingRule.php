<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property string $name Name of the rounding rule
 * @property int|null $lower_limit Start minute of the rounding range
 * @property int|null $upper_limit End minute of the rounding range
 * @property int|null $rounded_value Minute value after rounding
 * @property int|null $interval_minutes Rounding interval in minutes (e.g., 5, 6, 15)
 * @property string|null $apply_to Where the rule applies (check_in, check_out, or both)
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereLowerLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereUpperLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereRoundedValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereIntervalMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereApplyTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class RoundingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'rule_name',
        'lower_limit',
        'upper_limit',
        'rounded_value',
        'interval_minutes',
        'apply_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'lower_limit' => 'integer',
        'upper_limit' => 'integer',
        'rounded_value' => 'integer',
        'interval_minutes' => 'integer',
        'apply_to' => 'string',
    ];

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
