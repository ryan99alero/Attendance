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
        'pay_period_id',
        'reference_id',
        'reference_type',
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

    /**
     * Get the pay period this transaction belongs to
     */
    public function payPeriod()
    {
        return $this->belongsTo(PayPeriod::class);
    }

    /**
     * Create a vacation usage transaction
     */
    public static function createUsageTransaction($employeeId, $payPeriodId, $hoursUsed, $usageDate, $description = null)
    {
        return self::create([
            'employee_id' => $employeeId,
            'transaction_type' => 'usage',
            'hours' => -abs($hoursUsed), // Usage is always negative
            'transaction_date' => now(),
            'effective_date' => $usageDate,
            'pay_period_id' => $payPeriodId,
            'description' => $description ?: "Vacation usage - {$usageDate}",
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Create a vacation accrual transaction
     */
    public static function createAccrualTransaction($employeeId, $hoursAccrued, $accrualDate, $description = null, $accrualPeriod = null)
    {
        return self::create([
            'employee_id' => $employeeId,
            'transaction_type' => 'accrual',
            'hours' => abs($hoursAccrued), // Accrual is always positive
            'transaction_date' => now(),
            'effective_date' => $accrualDate,
            'accrual_period' => $accrualPeriod,
            'description' => $description ?: "Vacation accrual - {$accrualDate}",
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Calculate vacation balance for an employee
     */
    public static function calculateBalance($employeeId)
    {
        return self::where('employee_id', $employeeId)->sum('hours');
    }

    /**
     * Get vacation balance breakdown for an employee
     */
    public static function getBalanceBreakdown($employeeId)
    {
        $transactions = self::where('employee_id', $employeeId);

        return [
            'total_accrued' => $transactions->clone()->accruals()->sum('hours'),
            'total_used' => abs($transactions->clone()->usage()->sum('hours')),
            'total_adjustments' => $transactions->clone()->adjustments()->sum('hours'),
            'current_balance' => $transactions->sum('hours'),
        ];
    }
}
