<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Attendance; // Custom Logic

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

    // Custom Logic: Fetch attendance entries within the pay period
    public function fetchAttendance(): int
    {
        // Fetch attendance records within the pay period that have not been migrated
        $attendances = Attendance::whereBetween('check_in', [$this->start_date, $this->end_date])
            ->where('is_migrated', false)
            ->get();

        if ($attendances->isEmpty()) {
            return 0; // No records to migrate
        }

        // Prepare data for the punches table
        $punches = $attendances->map(function ($attendance) {
            return [
                'employee_id' => $attendance->employee_id,
                'device_id' => $attendance->device_id,
                'punch_type_id' => null, // Set null or determine type based on your business logic
                'time_in' => $attendance->check_in,
                'time_out' => $attendance->check_out,
                'is_altered' => false, // Default to false; update if needed
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        // Insert data into the punches table
        \DB::table('punches')->insert($punches);

        // Mark the attendance records as migrated
        $attendanceIds = $attendances->pluck('id')->toArray();
        Attendance::whereIn('id', $attendanceIds)->update(['is_migrated' => true]);

        // Return the count of records processed
        return count($punches);
    }
    // Custom Logic Ends
}
