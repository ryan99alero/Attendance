<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_half_day
 * @property float $hours_requested
 * @property string|null $notes
 * @property string $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read User|null $reviewer
 * @property-read User|null $creator
 */
class VacationRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'is_half_day',
        'hours_requested',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_half_day' => 'boolean',
            'hours_requested' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeDenied(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DENIED);
    }

    public function scopeForManager(Builder $query, int $managerId): Builder
    {
        $departmentIds = Department::where('manager_id', $managerId)->pluck('id');

        return $query->whereHas('employee', function (Builder $employeeQuery) use ($departmentIds) {
            $employeeQuery->whereIn('department_id', $departmentIds);
        });
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isDenied(): bool
    {
        return $this->status === self::STATUS_DENIED;
    }

    public static function calculateHoursRequested(Carbon $startDate, Carbon $endDate, bool $isHalfDay = false): float
    {
        if ($isHalfDay) {
            return 4.0;
        }

        $businessDays = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            if (! $current->isWeekend()) {
                $businessDays++;
            }
            $current->addDay();
        }

        return $businessDays * 8.0;
    }

    public function getBusinessDaysAttribute(): int
    {
        if ($this->is_half_day) {
            return 1;
        }

        $businessDays = 0;
        $current = $this->start_date->copy();

        while ($current <= $this->end_date) {
            if (! $current->isWeekend()) {
                $businessDays++;
            }
            $current->addDay();
        }

        return $businessDays;
    }

    public function getDateRangeAttribute(): string
    {
        if ($this->start_date->eq($this->end_date)) {
            return $this->start_date->format('M j, Y');
        }

        return $this->start_date->format('M j').' - '.$this->end_date->format('M j, Y');
    }
}
