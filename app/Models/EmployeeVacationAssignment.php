<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeVacationAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'vacation_policy_id',
        'effective_date',
        'end_date',
        'override_settings',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'override_settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the employee this assignment belongs to
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the vacation policy for this assignment
     */
    public function vacationPolicy()
    {
        return $this->belongsTo(VacationPolicy::class);
    }

    /**
     * Scope for active assignments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('effective_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Scope for assignments effective on a specific date
     */
    public function scopeEffectiveOn($query, $date)
    {
        return $query->where('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $date);
            });
    }
}
