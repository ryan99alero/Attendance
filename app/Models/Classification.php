<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id Primary key of the classifications table
 * @property string $name Name of the classification (e.g., Holiday, Vacation)
 * @property string $code Unique code identifier (e.g., HOLIDAY, VACATION)
 * @property string|null $description Detailed description of the classification
 * @property int $is_active Indicates if the classification is active
 * @property int|null $created_by Foreign key referencing the user who created the record
 * @property int|null $updated_by Foreign key referencing the user who last updated the record
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendance> $attendances
 * @property-read int|null $attendances_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Punch> $punches
 * @property-read int|null $punches_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classification whereUpdatedBy($value)
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
        'description', // Description of the classification
        'created_by', // User who created this record
        'updated_by', // User who last updated this record
    ];

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
