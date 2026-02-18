<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name Name of the punch type (e.g., Clock In, Clock Out)
 * @property string|null $description Description of the punch type
 * @property string|null $punch_direction Direction: 'start', 'stop', or null
 * @property bool $is_active Indicates if the punch type is active
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read User|null $updater
 *
 * @method static Builder<static>|PunchType newModelQuery()
 * @method static Builder<static>|PunchType newQuery()
 * @method static Builder<static>|PunchType query()
 * @method static Builder<static>|PunchType whereCreatedAt($value)
 * @method static Builder<static>|PunchType whereCreatedBy($value)
 * @method static Builder<static>|PunchType whereDescription($value)
 * @method static Builder<static>|PunchType whereId($value)
 * @method static Builder<static>|PunchType whereIsActive($value)
 * @method static Builder<static>|PunchType whereName($value)
 * @method static Builder<static>|PunchType wherePunchDirection($value)
 * @method static Builder<static>|PunchType whereUpdatedAt($value)
 * @method static Builder<static>|PunchType whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class PunchType extends Model
{
    use HasFactory;

    // Punch Type IDs
    public const CLOCK_IN = 1;

    public const CLOCK_OUT = 2;

    public const LUNCH_START = 3;

    public const LUNCH_STOP = 4;

    public const BREAK_START = 5;

    public const BREAK_END = 6;

    public const MANUAL_ENTRY = 7;

    public const JURY_DUTY = 8;

    public const BEREAVEMENT = 9;

    public const UNKNOWN = 12;

    // Direction constants
    public const DIRECTION_START = 'start';

    public const DIRECTION_STOP = 'stop';

    protected $fillable = [
        'name',
        'description',
        'punch_direction',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Check if this punch type represents starting/resuming work.
     */
    public function isStart(): bool
    {
        return $this->punch_direction === self::DIRECTION_START;
    }

    /**
     * Check if this punch type represents stopping/pausing work.
     */
    public function isStop(): bool
    {
        return $this->punch_direction === self::DIRECTION_STOP;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
