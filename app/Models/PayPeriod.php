<?php

namespace App\Models;

use App\Services\AttendanceProcessing\AttendanceProcessingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'is_processed',
        'processed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_processed' => 'boolean',
    ];

    /**
     * Relationship: Processor (User who processed the pay period).
     *
     * @return BelongsTo
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Relationship: Creator (User who created the record).
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Updater (User who last updated the record).
     *
     * @return BelongsTo
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Use the AttendanceProcessingService to process attendance.
     */
    public function processAttendance(): int
    {
        $service = new \App\Services\AttendanceProcessing\AttendanceProcessingService();
        $service->processAll($this);

        return 0; // Return count if needed
    }
}
