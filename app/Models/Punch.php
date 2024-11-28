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

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function device(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function punchType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PunchType::class, 'punch_type_id');
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
