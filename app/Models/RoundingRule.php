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
 * @property int $id Primary key of the rounding_rules table
 * @property int|null $round_group_id Foreign key referencing the round_groups table
 * @property int $minute_min Minimum minute value for the rounding range
 * @property int $minute_max Maximum minute value for the rounding range
 * @property int $new_minute New minute value after rounding
 * @property numeric $new_minute_decimal Decimal equivalent of the rounded minute value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RoundGroup|null $roundGroup
 * @method static Builder<static>|RoundingRule newModelQuery()
 * @method static Builder<static>|RoundingRule newQuery()
 * @method static Builder<static>|RoundingRule query()
 * @method static Builder<static>|RoundingRule whereCreatedAt($value)
 * @method static Builder<static>|RoundingRule whereId($value)
 * @method static Builder<static>|RoundingRule whereMinuteMax($value)
 * @method static Builder<static>|RoundingRule whereMinuteMin($value)
 * @method static Builder<static>|RoundingRule whereNewMinute($value)
 * @method static Builder<static>|RoundingRule whereNewMinuteDecimal($value)
 * @method static Builder<static>|RoundingRule whereRoundGroupId($value)
 * @method static Builder<static>|RoundingRule whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RoundingRule extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rounding_rules';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'round_group_id',
        'minute_min',
        'minute_max',
        'new_minute',
        'new_minute_decimal',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'minute_min' => 'integer',
        'minute_max' => 'integer',
        'new_minute' => 'integer',
        'new_minute_decimal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the round group associated with the rounding rule.
     *
     * @return BelongsTo
     */
    public function roundGroup(): BelongsTo
    {
        return $this->belongsTo(RoundGroup::class, 'round_group_id');
    }
}
