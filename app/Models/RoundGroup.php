<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 *
 *
 * @property int $id Primary key of the round_groups table
 * @property string|null $group_name Name of the rounding group (e.g., 5_Minute)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Employee> $employees
 * @property-read int|null $employees_count
 * @property-read Collection<int, RoundingRule> $roundingRules
 * @property-read int|null $rounding_rules_count
 * @method static Builder<static>|RoundGroup newModelQuery()
 * @method static Builder<static>|RoundGroup newQuery()
 * @method static Builder<static>|RoundGroup query()
 * @method static Builder<static>|RoundGroup whereCreatedAt($value)
 * @method static Builder<static>|RoundGroup whereGroupName($value)
 * @method static Builder<static>|RoundGroup whereId($value)
 * @method static Builder<static>|RoundGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RoundGroup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'group_name', // Add other fields here if applicable
    ];

    /**
     * Define the relationship to employees who use this round group.
     *
     * @return HasMany
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'round_group_id');
    }

    /**
     * Define the relationship to rounding rules associated with this group.
     *
     * @return HasMany
     */
    public function roundingRules(): HasMany
    {
        return $this->hasMany(RoundingRule::class, 'round_group_id');
    }
}
