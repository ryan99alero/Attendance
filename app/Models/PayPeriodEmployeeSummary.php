<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayPeriodEmployeeSummary extends Model
{
    use HasFactory;

    protected $table = 'pay_period_employee_summaries';

    protected $fillable = [
        'pay_period_id',
        'employee_id',
        'classification_id',
        'hours',
        'is_finalized',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
        'is_finalized' => 'boolean',
    ];

    // Relationships

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(Classification::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    public function scopeForPayPeriod($query, int $payPeriodId)
    {
        return $query->where('pay_period_id', $payPeriodId);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeFinalized($query)
    {
        return $query->where('is_finalized', true);
    }

    public function scopeNotFinalized($query)
    {
        return $query->where('is_finalized', false);
    }

    // Helper methods

    /**
     * Get all summaries for a pay period grouped by employee
     */
    public static function getEmployeeTotals(int $payPeriodId): array
    {
        return static::forPayPeriod($payPeriodId)
            ->with(['employee', 'classification'])
            ->get()
            ->groupBy('employee_id')
            ->map(function ($summaries) {
                $employee = $summaries->first()->employee;

                return [
                    'employee' => $employee,
                    'total_hours' => $summaries->sum('hours'),
                    'by_classification' => $summaries->mapWithKeys(function ($s) {
                        return [$s->classification->code ?? $s->classification->name => (float) $s->hours];
                    })->toArray(),
                ];
            })
            ->toArray();
    }
}
