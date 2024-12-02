<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Punch extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'device_id',
        'punch_type_id',
        'time_in',
        'time_out',
        'is_altered',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'time_in' => 'datetime',
        'time_out' => 'datetime',
        'is_altered' => 'boolean',
    ];

    /**
     * Automatically flag 'is_altered' when a record is updated.
     */
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($punch) {
            $dirtyFields = $punch->getDirty();

            // Ignore changes to 'is_altered'
            if (array_key_exists('is_altered', $dirtyFields)) {
                unset($dirtyFields['is_altered']);
            }

            // If any other field was changed, set 'is_altered' to true
            if (!empty($dirtyFields)) {
                $punch->is_altered = true;
            }
        });
    }

    /**
     * Relationship with Employee model.
     */
    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Relationship with Device model.
     */
    public function device(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * Relationship with PunchType model.
     */
    public function punchType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PunchType::class, 'punch_type_id');
    }

    /**
     * Relationship with User model for record creator.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with User model for record updater.
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
