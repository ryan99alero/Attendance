<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoundingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'minute_min',
        'minute_max',
        'new_minute',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'minute_min' => 'integer',
        'minute_max' => 'integer',
        'new_minute' => 'integer',
    ];

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
