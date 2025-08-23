<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int|null $employee_id Foreign key to Employees
 * @property string $card_number Unique card number assigned to the employee
 * @property bool $is_active Indicates if the card is currently active
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Employee|null $employee
 * @property-read User|null $updater
 * @method static Builder<static>|Card newModelQuery()
 * @method static Builder<static>|Card newQuery()
 * @method static Builder<static>|Card query()
 * @method static Builder<static>|Card whereCardNumber($value)
 * @method static Builder<static>|Card whereCreatedAt($value)
 * @method static Builder<static>|Card whereCreatedBy($value)
 * @method static Builder<static>|Card whereEmployeeId($value)
 * @method static Builder<static>|Card whereId($value)
 * @method static Builder<static>|Card whereIsActive($value)
 * @method static Builder<static>|Card whereUpdatedAt($value)
 * @method static Builder<static>|Card whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class Card extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'card_number',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the employee that owns the card.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the user who created the card.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the card.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
