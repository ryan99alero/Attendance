<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
