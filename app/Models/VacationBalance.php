<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $employee_id Foreign key to Employees
 * @property numeric $accrual_rate Rate at which vacation time accrues per pay period
 * @property numeric $accrued_hours Total vacation hours accrued
 * @property numeric $used_hours Total vacation hours used
 * @property numeric $carry_over_hours Vacation hours carried over from the previous year
 * @property numeric $cap_hours Maximum allowed vacation hours (cap)
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Employee $employee
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereAccrualRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereAccruedHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereCapHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereCarryOverHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VacationBalance whereUsedHours($value)
 * @mixin \Eloquent
 */
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
        // Anniversary-based accrual fields
        'accrual_year',
        'last_anniversary_date',
        'next_anniversary_date',
        'annual_days_earned',
        'previous_year_balance',
        'current_year_awarded',
        'current_year_used',
        'is_anniversary_based',
        'accrual_history',
        'policy_effective_date',
    ];

    protected $casts = [
        'accrual_rate' => 'decimal:2',
        'accrued_hours' => 'decimal:2',
        'used_hours' => 'decimal:2',
        'carry_over_hours' => 'decimal:2',
        'cap_hours' => 'decimal:2',
        // Anniversary-based accrual casts
        'last_anniversary_date' => 'date',
        'next_anniversary_date' => 'date',
        'annual_days_earned' => 'decimal:2',
        'previous_year_balance' => 'decimal:2',
        'current_year_awarded' => 'decimal:2',
        'current_year_used' => 'decimal:2',
        'is_anniversary_based' => 'boolean',
        'accrual_history' => 'json',
        'policy_effective_date' => 'date',
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

    /**
     * Get vacation transactions for this employee
     */
    public function transactions()
    {
        return $this->hasMany(VacationTransaction::class, 'employee_id', 'employee_id');
    }

    /**
     * Calculate balance from transactions (alternative source of truth)
     */
    public function getCalculatedBalanceAttribute()
    {
        return VacationTransaction::calculateBalance($this->employee_id);
    }

    /**
     * Get detailed balance breakdown from transactions
     */
    public function getTransactionBreakdownAttribute()
    {
        return VacationTransaction::getBalanceBreakdown($this->employee_id);
    }

    /**
     * Sync balance fields with transaction calculations
     * Useful for verifying data integrity or migrating to transaction-based system
     */
    public function syncWithTransactions()
    {
        $breakdown = $this->transaction_breakdown;

        $this->update([
            'accrued_hours' => $breakdown['total_accrued'],
            'used_hours' => $breakdown['total_used'],
        ]);

        return $this;
    }

    /**
     * Check if balance matches transaction calculations
     */
    public function isConsistentWithTransactions()
    {
        $breakdown = $this->transaction_breakdown;

        return (
            abs($this->accrued_hours - $breakdown['total_accrued']) < 0.01 &&
            abs($this->used_hours - $breakdown['total_used']) < 0.01
        );
    }
}
