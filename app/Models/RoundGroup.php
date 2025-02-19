<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
 *
 * @property int $id Primary key of the round_groups table
 * @property string|null $group_name Name of the rounding group (e.g., 5_Minute)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Employee> $employees
 * @property-read int|null $employees_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RoundingRule> $roundingRules
 * @property-read int|null $rounding_rules_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundGroup whereGroupName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoundGroup whereUpdatedAt($value)
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
