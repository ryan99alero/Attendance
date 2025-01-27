<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
