<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\Classification;
use App\Models\VacationCalendar;
use App\Models\HolidayTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceClassificationService
{
    private array $classificationCache = [];

    public function __construct()
    {
        $this->loadClassificationCache();
    }

    /**
     * Load classification IDs into cache for performance
     */
    private function loadClassificationCache(): void
    {
        $classifications = Classification::all();
        foreach ($classifications as $classification) {
            $this->classificationCache[$classification->code] = $classification->id;
        }
    }

    /**
     * Classify all unclassified attendance records
     */
    public function classifyAllUnclassifiedAttendance(): void
    {
        Log::info('[AttendanceClassification] Starting classification of all unclassified attendance records');

        $unclassifiedAttendance = Attendance::whereNull('classification_id')->get();
        Log::info("[AttendanceClassification] Found {$unclassifiedAttendance->count()} unclassified attendance records");

        $classificationCounts = [
            'REGULAR' => 0,
            'VACATION' => 0,
            'HOLIDAY' => 0,
            'SICK' => 0,
            'TRAINING' => 0,
            'REMOTE' => 0,
            'UNCLASSIFIED' => 0,
        ];

        foreach ($unclassifiedAttendance as $attendance) {
            $classificationCode = $this->determineClassification($attendance);

            if ($classificationCode && isset($this->classificationCache[$classificationCode])) {
                $attendance->update(['classification_id' => $this->classificationCache[$classificationCode]]);
                $classificationCounts[$classificationCode]++;

                Log::debug("[AttendanceClassification] Classified Attendance ID {$attendance->id} as {$classificationCode}");
            } else {
                $classificationCounts['UNCLASSIFIED']++;
                Log::warning("[AttendanceClassification] Could not classify Attendance ID {$attendance->id}");
            }
        }

        Log::info('[AttendanceClassification] Classification summary:');
        foreach ($classificationCounts as $type => $count) {
            if ($count > 0) {
                Log::info("[AttendanceClassification] {$type}: {$count} records");
            }
        }

        Log::info('[AttendanceClassification] Completed classification of attendance records');
    }

    /**
     * Classify a single attendance record
     */
    public function classifyAttendance(Attendance $attendance): ?string
    {
        $classificationCode = $this->determineClassification($attendance);

        if ($classificationCode && isset($this->classificationCache[$classificationCode])) {
            $attendance->update(['classification_id' => $this->classificationCache[$classificationCode]]);
            Log::info("[AttendanceClassification] Classified Attendance ID {$attendance->id} as {$classificationCode}");
            return $classificationCode;
        }

        Log::warning("[AttendanceClassification] Could not classify Attendance ID {$attendance->id}");
        return null;
    }

    /**
     * Determine the appropriate classification for an attendance record
     */
    private function determineClassification(Attendance $attendance): ?string
    {
        // 1. Check if it's explicitly a vacation record
        if ($this->isVacationRecord($attendance)) {
            return 'VACATION';
        }

        // 2. Check if it's explicitly a holiday record
        if ($this->isHolidayRecord($attendance)) {
            return 'HOLIDAY';
        }

        // 3. Check if it's sick leave (based on notes or manual entry patterns)
        if ($this->isSickRecord($attendance)) {
            return 'SICK';
        }

        // 4. Check if it's training (based on notes or manual entry patterns)
        if ($this->isTrainingRecord($attendance)) {
            return 'TRAINING';
        }

        // 5. Check if it's remote work (based on notes or patterns)
        if ($this->isRemoteWorkRecord($attendance)) {
            return 'REMOTE';
        }

        // 6. Default to regular work if it's a normal clock in/out pattern
        if ($this->isRegularWorkRecord($attendance)) {
            return 'REGULAR';
        }

        // Unable to classify
        return null;
    }

    /**
     * Check if attendance record is vacation-related
     */
    private function isVacationRecord(Attendance $attendance): bool
    {
        // Check if generated from vacation calendar
        $notes = strtolower($attendance->issue_notes ?? '');
        if (str_contains($notes, 'vacation') || str_contains($notes, 'generated from vacation calendar')) {
            return true;
        }

        // Check if there's a vacation calendar entry for this employee and date
        $attendanceDate = Carbon::parse($attendance->punch_time)->toDateString();
        $vacationEntry = VacationCalendar::where('employee_id', $attendance->employee_id)
            ->whereDate('vacation_date', $attendanceDate)
            ->where('is_active', true)
            ->exists();

        return $vacationEntry;
    }

    /**
     * Check if attendance record is holiday-related
     */
    private function isHolidayRecord(Attendance $attendance): bool
    {
        // Check if explicitly marked as holiday
        if (!is_null($attendance->holiday_id)) {
            return true;
        }

        // Check notes for holiday indicators
        $notes = strtolower($attendance->issue_notes ?? '');
        if (str_contains($notes, 'holiday') || str_contains($notes, 'generated from holiday')) {
            return true;
        }

        // Check if the date is a recognized holiday
        $attendanceDate = Carbon::parse($attendance->punch_time)->toDateString();
        // Note: You could add logic here to check against HolidayTemplate or other holiday sources

        return false;
    }

    /**
     * Check if attendance record is sick leave
     */
    private function isSickRecord(Attendance $attendance): bool
    {
        $notes = strtolower($attendance->issue_notes ?? '');
        return str_contains($notes, 'sick') || str_contains($notes, 'illness');
    }

    /**
     * Check if attendance record is training
     */
    private function isTrainingRecord(Attendance $attendance): bool
    {
        $notes = strtolower($attendance->issue_notes ?? '');
        return str_contains($notes, 'training') || str_contains($notes, 'course') || str_contains($notes, 'seminar');
    }

    /**
     * Check if attendance record is remote work
     */
    private function isRemoteWorkRecord(Attendance $attendance): bool
    {
        $notes = strtolower($attendance->issue_notes ?? '');
        return str_contains($notes, 'remote') || str_contains($notes, 'home') || str_contains($notes, 'wfh');
    }

    /**
     * Check if attendance record represents regular work
     */
    private function isRegularWorkRecord(Attendance $attendance): bool
    {
        // Check if it's a standard punch type (Clock In, Clock Out, Lunch, Break)
        $punchType = $attendance->punchType;
        if (!$punchType) {
            return false;
        }

        $regularPunchTypes = ['Clock In', 'Clock Out', 'Lunch Start', 'Lunch Stop', 'Break Start', 'Break End'];

        // If it's a regular punch type and not manually created with special notes, it's likely regular work
        return in_array($punchType->name, $regularPunchTypes) &&
               !$this->hasSpecialTimeOffIndicators($attendance);
    }

    /**
     * Check if attendance record has indicators of special time off
     */
    private function hasSpecialTimeOffIndicators(Attendance $attendance): bool
    {
        $notes = strtolower($attendance->issue_notes ?? '');
        $specialIndicators = ['vacation', 'holiday', 'sick', 'training', 'remote', 'time off', 'pto'];

        foreach ($specialIndicators as $indicator) {
            if (str_contains($notes, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get classification ID by code
     */
    public function getClassificationId(string $code): ?int
    {
        return $this->classificationCache[$code] ?? null;
    }

    /**
     * Get all classification mappings
     */
    public function getClassificationMappings(): array
    {
        return $this->classificationCache;
    }
}