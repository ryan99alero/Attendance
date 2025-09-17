<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VacationPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_name',
        'min_tenure_years',
        'max_tenure_years',
        'vacation_days_per_year',
        'vacation_hours_per_year',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'vacation_days_per_year' => 'decimal:2',
        'vacation_hours_per_year' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get all employee vacation assignments for this policy
     */
    public function employeeAssignments()
    {
        return $this->hasMany(EmployeeVacationAssignment::class);
    }

    /**
     * Get all employees currently assigned to this policy
     */
    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_vacation_assignments')
            ->withPivot('effective_date', 'end_date', 'override_settings', 'is_active')
            ->withTimestamps();
    }

    /**
     * Scope to get active policies only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get policy for specific tenure years
     */
    public function scopeForTenure($query, int $years)
    {
        return $query->where('min_tenure_years', '<=', $years)
            ->where(function ($q) use ($years) {
                $q->whereNull('max_tenure_years')
                  ->orWhere('max_tenure_years', '>=', $years);
            });
    }
}
