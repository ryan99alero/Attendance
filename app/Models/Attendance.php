<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'device_id',
        'punch_time',
        'status',
        'issue_notes',
        'is_manual',
        'created_by',
        'updated_by',
        'is_migrated',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'punch_time' => 'datetime:Y-m-d\TH:i', // ISO 8601 format
        'is_manual' => 'boolean',
        'is_migrated' => 'boolean',
    ];

    /**
     * Relationship with the `Employee` model.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship with the `Device` model.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Relationship with the `User` model for the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with the `User` model for the updater.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to filter attendance records by status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter attendance records within a time range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startTime
     * @param string $endTime
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithinTimeRange($query, string $startTime, string $endTime)
    {
        return $query->whereBetween('punch_time', [$startTime, $endTime]);
    }

    /**
     * Automatically set the `created_by` and `updated_by` fields.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
