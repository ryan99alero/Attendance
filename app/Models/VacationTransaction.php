<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class VacationTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'transaction_type',
        'hours',
        'transaction_date',
        'effective_date',
        'accrual_period',
        'description',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
        'transaction_date' => 'date',
        'effective_date' => 'date',
        'metadata' => 'array',
    ];

    /**
     * Get the employee this transaction belongs to
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope for accrual transactions
     */
    public function scopeAccruals($query)
    {
        return $query->where('transaction_type', 'accrual');
    }

    /**
     * Scope for usage transactions
     */
    public function scopeUsage($query)
    {
        return $query->where('transaction_type', 'usage');
    }

    /**
     * Scope for adjustments
     */
    public function scopeAdjustments($query)
    {
        return $query->where('transaction_type', 'adjustment');
    }

    /**
     * Scope for specific date range
     */
    public function scopeDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('transaction_date', [$start, $end]);
    }

    /**
     * Scope for specific accrual period
     */
    public function scopeForPeriod($query, string $period)
    {
        return $query->where('accrual_period', $period);
    }
}
