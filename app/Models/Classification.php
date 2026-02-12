<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id Primary key of the classifications table
 * @property string $name Name of the classification (e.g., Holiday, Vacation)
 * @property string $code Unique code identifier (e.g., HOLIDAY, VACATION)
 * @property string|null $description Detailed description of the classification
 * @property int $is_active Indicates if the classification is active
 * @property int|null $created_by Foreign key referencing the user who created the record
 * @property int|null $updated_by Foreign key referencing the user who last updated the record
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Attendance> $attendances
 * @property-read int|null $attendances_count
 * @property-read Collection<int, Punch> $punches
 * @property-read int|null $punches_count
 *
 * @method static Builder<static>|Classification newModelQuery()
 * @method static Builder<static>|Classification newQuery()
 * @method static Builder<static>|Classification query()
 * @method static Builder<static>|Classification whereCode($value)
 * @method static Builder<static>|Classification whereCreatedAt($value)
 * @method static Builder<static>|Classification whereCreatedBy($value)
 * @method static Builder<static>|Classification whereDescription($value)
 * @method static Builder<static>|Classification whereId($value)
 * @method static Builder<static>|Classification whereIsActive($value)
 * @method static Builder<static>|Classification whereName($value)
 * @method static Builder<static>|Classification whereUpdatedAt($value)
 * @method static Builder<static>|Classification whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class Classification extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'classifications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', // The name of the classification
        'code', // Unique code identifier
        'adp_code', // ADP hour code (H, V, S, D, P, etc.)
        'is_regular', // Maps to standard Reg Hours column
        'is_overtime', // Maps to standard O/T Hours column
        'description', // Description of the classification
        'created_by', // User who created this record
        'updated_by', // User who last updated this record
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_regular' => 'boolean',
            'is_overtime' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope to get classifications that map to regular hours
     */
    public function scopeRegular($query)
    {
        return $query->where('is_regular', true);
    }

    /**
     * Scope to get classifications that map to overtime hours
     */
    public function scopeOvertime($query)
    {
        return $query->where('is_overtime', true);
    }

    /**
     * Scope to get classifications with ADP codes (Hours 3 types)
     */
    public function scopeWithAdpCode($query)
    {
        return $query->whereNotNull('adp_code');
    }

    /**
     * Relationships to other models.
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function punches()
    {
        return $this->hasMany(Punch::class);
    }

    /**
     * Boot method for model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically handle created_by and updated_by fields
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
