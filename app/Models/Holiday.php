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
 * @property string $name Name of the holiday
 * @property string|null $start_date
 * @property string|null $end_date
 * @property bool $is_recurring Indicates if the holiday recurs annually
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @method static Builder<static>|Holiday newModelQuery()
 * @method static Builder<static>|Holiday newQuery()
 * @method static Builder<static>|Holiday query()
 * @method static Builder<static>|Holiday whereCreatedAt($value)
 * @method static Builder<static>|Holiday whereCreatedBy($value)
 * @method static Builder<static>|Holiday whereEndDate($value)
 * @method static Builder<static>|Holiday whereId($value)
 * @method static Builder<static>|Holiday whereIsRecurring($value)
 * @method static Builder<static>|Holiday whereName($value)
 * @method static Builder<static>|Holiday whereStartDate($value)
 * @method static Builder<static>|Holiday whereUpdatedAt($value)
 * @method static Builder<static>|Holiday whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class Holiday extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_recurring',
        'created_by',
        'updated_by',
    ];

    /**
     * Cast attributes to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
    ];

    /**
     * Get the user who created the holiday.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the holiday.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
