<?php

namespace App\Services\AttendanceProcessing;

use App\Models\Attendance;
use App\Models\Classification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceClassificationService
{
    private array $classificationCache = [];

    private array $punchTypeNameCache = [];

    private array $vacationDatesCache = [];

    private bool $vacationCacheLoaded = false;

    public function __construct()
    {
        $this->loadClassificationCache();
        $this->loadPunchTypeCache();
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
     * Load punch type names into cache for performance
     */
    private function loadPunchTypeCache(): void
    {
        $punchTypes = DB::table('punch_types')->get();
        foreach ($punchTypes as $punchType) {
            $this->punchTypeNameCache[$punchType->id] = $punchType->name;
        }
    }

    /**
     * Classify all unclassified attendance records
     */
    public function classifyAllUnclassifiedAttendance(): void
    {
        // Disable query logging to prevent memory exhaustion
        DB::disableQueryLog();

        $totalCount = Attendance::whereNull('classification_id')->count();
        Log::info("[AttendanceClassification] Found {$totalCount} unclassified attendance records");

        // Pre-load vacation dates for the date range we're processing
        $this->preloadVacationDates();

        $classificationCounts = [
            'REGULAR' => 0,
            'VACATION' => 0,
            'HOLIDAY' => 0,
            'SICK' => 0,
            'TRAINING' => 0,
            'REMOTE' => 0,
            'UNCLASSIFIED' => 0,
        ];

        // Use cursor() to iterate one record at a time
        foreach (Attendance::whereNull('classification_id')->cursor() as $attendance) {
            $classificationCode = $this->determineClassification($attendance);

            if ($classificationCode && isset($this->classificationCache[$classificationCode])) {
                $attendance->update(['classification_id' => $this->classificationCache[$classificationCode]]);
                $classificationCounts[$classificationCode]++;
            } else {
                $classificationCounts['UNCLASSIFIED']++;
            }
        }

        Log::info('[AttendanceClassification] Classification summary: '.json_encode(array_filter($classificationCounts)));
    }

    /**
     * Pre-load vacation dates to avoid N+1 queries
     */
    private function preloadVacationDates(): void
    {
        if ($this->vacationCacheLoaded) {
            return;
        }

        // Get all active vacation records and cache by employee_id + date
        $vacations = DB::table('vacation_calendars')
            ->where('is_active', true)
            ->select('employee_id', 'vacation_date')
            ->get();

        foreach ($vacations as $vacation) {
            $key = $vacation->employee_id.'_'.$vacation->vacation_date;
            $this->vacationDatesCache[$key] = true;
        }

        $this->vacationCacheLoaded = true;
    }

    // AUDIT: 2026-02-13 - classifyAttendance() never called externally
    // classifyAllUnclassifiedAttendance() is the main entry point which calls determineClassification() directly
    /*
    **
     * Classify a single attendance record
     *
    public function classifyAttendance(Attendance $attendance): ?string
    {
        $classificationCode = $this->determineClassification($attendance);

        if ($classificationCode && isset($this->classificationCache[$classificationCode])) {
            $attendance->update(['classification_id' => $this->classificationCache[$classificationCode]]);

            return $classificationCode;
        }

        return null;
    }
    */

    /**
     * Determine the appropriate classification for an attendance record
     */
    private function determineClassification(Attendance $attendance): ?string
    {
        if ($this->isVacationRecord($attendance)) {
            return 'VACATION';
        }

        if ($this->isHolidayRecord($attendance)) {
            return 'HOLIDAY';
        }

        if ($this->isSickRecord($attendance)) {
            return 'SICK';
        }

        if ($this->isTrainingRecord($attendance)) {
            return 'TRAINING';
        }

        if ($this->isRemoteWorkRecord($attendance)) {
            return 'REMOTE';
        }

        if ($this->isRegularWorkRecord($attendance)) {
            return 'REGULAR';
        }

        return null;
    }

    private function isVacationRecord(Attendance $attendance): bool
    {
        $notes = strtolower($attendance->issue_notes ?? '');
        if (str_contains($notes, 'vacation') || str_contains($notes, 'generated from vacation calendar')) {
            return true;
        }

        // Use cached vacation dates to avoid N+1 queries
        $attendanceDate = Carbon::parse($attendance->punch_time)->toDateString();
        $key = $attendance->employee_id.'_'.$attendanceDate;

        return isset($this->vacationDatesCache[$key]);
    }

    private function isHolidayRecord(Attendance $attendance): bool
    {
        if (! is_null($attendance->holiday_id)) {
            return true;
        }

        $notes = strtolower($attendance->issue_notes ?? '');

        return str_contains($notes, 'holiday') || str_contains($notes, 'generated from holiday');
    }

    private function isSickRecord(Attendance $attendance): bool
    {
        $notes = strtolower($attendance->issue_notes ?? '');

        return str_contains($notes, 'sick') || str_contains($notes, 'illness');
    }

    private function isTrainingRecord(Attendance $attendance): bool
    {
        $notes = strtolower($attendance->issue_notes ?? '');

        return str_contains($notes, 'training') || str_contains($notes, 'course') || str_contains($notes, 'seminar');
    }

    private function isRemoteWorkRecord(Attendance $attendance): bool
    {
        $notes = strtolower($attendance->issue_notes ?? '');

        return str_contains($notes, 'remote') || str_contains($notes, 'home') || str_contains($notes, 'wfh');
    }

    private function isRegularWorkRecord(Attendance $attendance): bool
    {
        // Use cached punch type names to avoid N+1 queries
        if (! $attendance->punch_type_id) {
            return false;
        }

        $punchTypeName = $this->punchTypeNameCache[$attendance->punch_type_id] ?? null;
        if (! $punchTypeName) {
            return false;
        }

        $regularPunchTypes = ['Clock In', 'Clock Out', 'Lunch Start', 'Lunch Stop', 'Break Start', 'Break End'];

        return in_array($punchTypeName, $regularPunchTypes) &&
               ! $this->hasSpecialTimeOffIndicators($attendance);
    }

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

    public function getClassificationId(string $code): ?int
    {
        return $this->classificationCache[$code] ?? null;
    }

    // AUDIT: 2026-02-13 - getClassificationMappings() never called externally
    /*
    public function getClassificationMappings(): array
    {
        return $this->classificationCache;
    }
    */
}
