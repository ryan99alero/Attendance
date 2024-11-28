<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VacationBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'accrual_rate',
        'accrued_hours',
        'used_hours',
        'carry_over_hours',
        'cap_hours',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'accrual_rate' => 'decimal:2',
        'accrued_hours' => 'decimal:2',
        'used_hours' => 'decimal:2',
        'carry_over_hours' => 'decimal:2',
        'cap_hours' => 'decimal:2',
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
