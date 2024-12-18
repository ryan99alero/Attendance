<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name Name of the rounding rule
 * @property int|null $minute_min Minimum minute value for the rounding range
 * @property int|null $minute_max Maximum minute value for the rounding range
 * @property int|null $new_minute New minute value after rounding
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereMinuteMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereMinuteMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereNewMinute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundingRule whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class RoundingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'minute_min',
        'minute_max',
        'new_minute',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'minute_min' => 'integer',
        'minute_max' => 'integer',
        'new_minute' => 'integer',
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
