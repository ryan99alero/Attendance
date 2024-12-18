<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name Name of the punch type (e.g., Clock In, Clock Out)
 * @property string|null $description Description of the punch type
 * @property string|null $schedule_reference
 * @property bool $is_active Indicates if the punch type is active
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType whereScheduleReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PunchType whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class PunchType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'schedule_reference',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
