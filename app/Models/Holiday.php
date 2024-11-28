<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'date',
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
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the holiday.
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
