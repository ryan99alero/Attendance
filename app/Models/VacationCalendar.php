<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VacationCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'vacation_date',
        'is_half_day',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'vacation_date' => 'date',
        'is_half_day' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
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
