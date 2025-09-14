<?php

namespace App\Services\TimeGrouping;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\CompanySetup;
use App\Services\Shift\ShiftSchedulePunchTypeAssignmentService;
use App\Services\Heuristic\HeuristicPunchTypeAssignmentService;
use App\Services\ML\MLPunchTypePredictorService;
use App\Services\TimeGrouping\AttendanceTimeGroupService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceTimeProcessorService
{
    protected ShiftSchedulePunchTypeAssignmentService $shiftScheduleService;
    protected HeuristicPunchTypeAssignmentService $heuristicService;
    protected MLPunchTypePredictorService $mlService;
    protected AttendanceTimeGroupService $attendanceTimeGroupService;

    public function __construct(
        ShiftSchedulePunchTypeAssignmentService $shiftScheduleService,
        HeuristicPunchTypeAssignmentService $heuristicService,
        MLPunchTypePredictorService $mlService,
        AttendanceTimeGroupService $attendanceTimeGroupService
    ) {
        $this->shiftScheduleService = $shiftScheduleService;
        $this->heuristicService = $heuristicService;
        $this->mlService = $mlService;
        $this->attendanceTimeGroupService = $attendanceTimeGroupService;
        Log::info("[Processor] Initialized AttendanceTimeProcessorService.");
    }

    public function processAttendanceForPayPeriod(PayPeriod $payPeriod): void
    {
        Log::info("[AttendanceTimeProcessorService] Starting attendance processing for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();
        if ($endDate->greaterThanOrEqualTo(Carbon::today())) {
            $endDate = $endDate->subDay();
        }

        $companySetup = CompanySetup::first();
        $flexibility = $companySetup->attendance_flexibility_minutes ?? 30;

        $attendances = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->whereIn('status', ['Incomplete', 'NeedsReview'])
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get()
            ->groupBy('employee_id');

        Log::info("[AttendanceTimeProcessorService] 📌 Found {$attendances->count()} incomplete attendance records.");

        foreach ($attendances as $employeeId => $punches) {
            Log::info("[Processor] Processing Employee ID: {$employeeId} with {$punches->count()} punch(es).");

            // ✅ First: Assign shift_date to all punches before processing
            foreach ($punches as $punch) {
                if (empty($punch->shift_date)) {
                    $this->attendanceTimeGroupService->getOrCreateShiftDate($punch, auth()->id() ?? 0);
                    $punch->save(); // Save the updated shift_date
                    Log::info("[Processor] Assigned shift_date {$punch->shift_date} to Punch ID: {$punch->id}");
                }
            }

            // ✅ Second: Handle odd-numbered punch days by processing pairs and marking unpaired punch
            $punchDays = $punches->groupBy('shift_date');
            $unpairedPunches = collect();

            foreach ($punchDays as $shiftDate => $dayPunches) {
                if ($dayPunches->count() % 2 !== 0) {
                    Log::warning("[Processor] Employee {$employeeId} has {$dayPunches->count()} punches on shift date {$shiftDate} - will process pairs and mark unpaired punch");

                    // Find the unpaired punch (usually the middle one that breaks the pattern)
                    $sortedPunches = $dayPunches->sortBy('punch_time');
                    $unpairedPunch = $this->findUnpairedPunch($sortedPunches);

                    if ($unpairedPunch) {
                        $unpairedPunch->status = 'NeedsReview';
                        $unpairedPunch->punch_type_id = null;
                        $unpairedPunch->punch_state = 'unknown';
                        $unpairedPunch->issue_notes = "Unpaired punch detected on {$shiftDate} - requires manual review";
                        $unpairedPunch->save();

                        // Remove only the unpaired punch from processing, let the pairs continue
                        $unpairedPunches->push($unpairedPunch);
                        Log::info("[Processor] Marked punch ID {$unpairedPunch->id} as unpaired, continuing with remaining pairs");
                    }
                }
            }

            // Remove unpaired punches from processing but keep the pairs
            if ($unpairedPunches->isNotEmpty()) {
                $punches = $punches->reject(function($punch) use ($unpairedPunches) {
                    return $unpairedPunches->pluck('id')->contains($punch->id);
                });
                Log::info("[Processor] Removed {$unpairedPunches->count()} unpaired punches, continuing with {$punches->count()} paired punches");
            }

            $punchEvaluations = []; // ✅ Initialize array to collect punch evaluation results

            $debugMode = $companySetup->debug_punch_assignment_mode ?? 'full';

            // ✅ Check if ML mode needs training data first
            if ($debugMode === 'ml') {
                $trainingDataCount = \DB::table('attendances')
                    ->whereNotNull('punch_type_id')
                    ->where('status', 'Complete')
                    ->count();

                if ($trainingDataCount < 50) {
                    Log::info("[Processor] ML mode detected but insufficient training data ({$trainingDataCount} records). Running training phase first.");

                    // Run Heuristic first to create training data
                    Log::info("[Processor] Training Phase: Running Heuristic Punch Assignment for Employee ID: {$employeeId}.");
                    $this->heuristicService->assignPunchTypes($punches, $flexibility, $punchEvaluations);

                    // Run Shift Schedule for any remaining punches
                    $pendingPunches = $punches->whereNull('punch_type_id');
                    if ($pendingPunches->isNotEmpty()) {
                        Log::info("[Processor] Training Phase: Running Shift-Based Punch Assignment for Employee ID: {$employeeId}.");
                        $this->shiftScheduleService->assignPunchTypes($pendingPunches, $flexibility, $punchEvaluations);
                    }

                    Log::info("[Processor] Training phase completed. These processed records will become ML training data after migration.");
                } else {
                    Log::info("[Processor] Sufficient training data available ({$trainingDataCount} records). Running ML mode.");
                    // Only run ML when we have enough training data
                    $this->mlService->assignPunchTypes($punches, $employeeId, $punchEvaluations);
                }
            } else {
                // ✅ 1️⃣ Run Heuristic Processing First
                if ($debugMode === 'heuristic' || $debugMode === 'full') {
                    Log::info("[Processor] Running Heuristic Punch Assignment for Employee ID: {$employeeId}.");
                    $this->heuristicService->assignPunchTypes($punches, $flexibility, $punchEvaluations);
                }

                // ✅ 2️⃣ Process Any Punches That Still Need a Punch Type Using Shift Logic
                $pendingPunches = $punches->whereNull('punch_type_id');
                if ($pendingPunches->isNotEmpty() && ($debugMode === 'shift_schedule' || $debugMode === 'full')) {
                    Log::info("[Processor] Running Shift-Based Punch Assignment for Employee ID: {$employeeId}.");
                    $this->shiftScheduleService->assignPunchTypes($pendingPunches, $flexibility, $punchEvaluations);
                }

                // ✅ 3️⃣ Process Any Remaining Unresolved Punches Using ML
                $mlPendingPunches = $punches->whereNull('punch_type_id');
                if ($mlPendingPunches->isNotEmpty() && ($debugMode === 'full' && $companySetup->use_ml_for_punch_matching)) {
                    Log::info("[Processor] Running ML Punch Assignment for Employee ID: {$employeeId}.");
                    $this->mlService->assignPunchTypes($mlPendingPunches, $employeeId, $punchEvaluations);
                }
            }

            // ✅ 4️⃣ FINALIZE Punch Types for Employee
            $this->finalizePunchTypes($punches, $punchEvaluations);

            Log::info("[Processor] Punch Type Evaluations Completed for Employee ID: {$employeeId}");
        }

        Log::info("[AttendanceTimeProcessorService] ✅ Completed attendance processing for PayPeriod ID: {$payPeriod->id}");
    }

    private function finalizePunchTypes($punches, &$punchEvaluations): void
    {
        foreach ($punches as $punch) {
            $punchId = $punch->id;
            $evaluations = $punchEvaluations[$punchId] ?? [];

            if (empty($evaluations)) {
                Log::warning("[Processor] No evaluations found for Punch ID: {$punchId}. Marking as NeedsReview.");
                $punch->status = 'NeedsReview';
                $punch->issue_notes = "No confident Punch Type assigned.";
                $punch->save();
                continue;
            }

            // Determine final Punch Type by confidence (ML prioritized, fallback to Heuristic, then Shift)
            $finalType = $this->selectFinalPunchType($evaluations);
            $finalState = $this->selectFinalPunchState($evaluations);

            if ($finalType) {
                $punch->punch_type_id = $finalType;
                $punch->punch_state = $finalState;
                $punch->status = 'Complete';
                $punch->issue_notes = "Finalized Punch Type from multiple evaluations.";
                $punch->save();

                Log::info("[Processor] Assigned Punch ID: {$punchId} -> Type: {$finalType}, State: {$finalState}");
            } else {
                Log::warning("[Processor] No confident decision for Punch ID: {$punchId}. Marking as NeedsReview.");
                $punch->status = 'NeedsReview';
                $punch->issue_notes = "Could not determine Punch Type.";
                $punch->save();
            }
        }
    }

    private function selectFinalPunchType($evaluations): ?int
    {
        // Prioritize ML -> Heuristic -> Shift
        if (isset($evaluations['ml']['punch_type_id'])) {
            return $evaluations['ml']['punch_type_id'];
        }

        if (isset($evaluations['heuristic']['punch_type_id'])) {
            return $evaluations['heuristic']['punch_type_id'];
        }

        if (isset($evaluations['shift']['punch_type_id'])) {
            return $evaluations['shift']['punch_type_id'];
        }

        return null;
    }

    private function selectFinalPunchState($evaluations): string
    {
        if (isset($evaluations['ml']['punch_state'])) {
            return $evaluations['ml']['punch_state'];
        }

        if (isset($evaluations['heuristic']['punch_state'])) {
            return $evaluations['heuristic']['punch_state'];
        }

        if (isset($evaluations['shift']['punch_state'])) {
            return $evaluations['shift']['punch_state'];
        }

        return 'unknown';
    }

    /**
     * Find the unpaired punch in an odd-numbered set of punches
     * This identifies punches that break the normal pairing pattern
     */
    private function findUnpairedPunch($sortedPunches)
    {
        $punches = $sortedPunches->values(); // Reset keys to 0, 1, 2...
        $count = $punches->count();

        // For 7 punches, we expect: Clock In, Break/Lunch Start, Break/Lunch Stop, Lunch/Break Start, Lunch/Break Stop, Break Start, Clock Out
        // The unpaired punch is usually in the middle where the pattern breaks

        // Simple heuristic: look for timing gaps or pattern breaks
        // If we have 7 punches, typically one will be out of sequence

        // Method 1: Look for the punch that creates the biggest time gap when removed
        $bestCandidate = null;
        $smallestGapSum = PHP_INT_MAX;

        for ($i = 0; $i < $count; $i++) {
            // Create array without this punch
            $withoutPunch = $punches->reject(function($punch, $index) use ($i) {
                return $index === $i;
            })->values();

            // Calculate total gaps between consecutive punches
            $gapSum = 0;
            for ($j = 0; $j < $withoutPunch->count() - 1; $j++) {
                $time1 = strtotime($withoutPunch[$j]->punch_time);
                $time2 = strtotime($withoutPunch[$j + 1]->punch_time);
                $gap = abs($time2 - $time1);
                $gapSum += $gap;
            }

            // The punch that creates the most regular pattern when removed is likely the unpaired one
            if ($gapSum < $smallestGapSum) {
                $smallestGapSum = $gapSum;
                $bestCandidate = $punches[$i];
            }
        }

        if ($bestCandidate) {
            Log::info("[Processor] Identified punch ID {$bestCandidate->id} at {$bestCandidate->punch_time} as unpaired");
        }

        return $bestCandidate;
    }
}
