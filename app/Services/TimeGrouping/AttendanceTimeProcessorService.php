<?php

namespace App\Services\TimeGrouping;

use App\Models\Attendance;
use App\Models\CompanySetup;
use App\Models\PayPeriod;
use App\Services\Consensus\PunchTypeConsensusService;
use App\Services\Heuristic\HeuristicPunchTypeAssignmentService;
use App\Services\ML\MLPunchTypePredictorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceTimeProcessorService
{
    protected HeuristicPunchTypeAssignmentService $heuristicService;

    protected MLPunchTypePredictorService $mlService;

    protected PunchTypeConsensusService $consensusService;

    protected AttendanceTimeGroupService $attendanceTimeGroupService;

    public function __construct(
        HeuristicPunchTypeAssignmentService $heuristicService,
        MLPunchTypePredictorService $mlService,
        PunchTypeConsensusService $consensusService,
        AttendanceTimeGroupService $attendanceTimeGroupService
    ) {
        $this->heuristicService = $heuristicService;
        $this->mlService = $mlService;
        $this->consensusService = $consensusService;
        $this->attendanceTimeGroupService = $attendanceTimeGroupService;
    }

    public function processAttendanceForPayPeriod(PayPeriod $payPeriod): void
    {
        // Disable query logging to prevent memory exhaustion
        DB::disableQueryLog();

        Log::info("[AttendanceTimeProcessorService] Starting attendance processing for PayPeriod ID: {$payPeriod->id}");

        $startDate = Carbon::parse($payPeriod->start_date)->startOfDay();
        $endDate = Carbon::parse($payPeriod->end_date)->endOfDay();
        if ($endDate->greaterThanOrEqualTo(Carbon::today())) {
            $endDate = $endDate->subDay();
        }

        $companySetup = CompanySetup::first();
        $flexibility = $companySetup->attendance_flexibility_minutes ?? 30;

        // Get distinct employee IDs that have records to process (memory-efficient)
        $employeeIds = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->whereIn('status', ['Incomplete', 'NeedsReview'])
            ->distinct()
            ->pluck('employee_id');

        $totalRecords = Attendance::whereBetween('punch_time', [$startDate, $endDate])
            ->whereIn('status', ['Incomplete', 'NeedsReview'])
            ->count();

        Log::info("[AttendanceTimeProcessorService] Found {$totalRecords} incomplete records across {$employeeIds->count()} employees.");

        $debugMode = $companySetup->debug_punch_assignment_mode ?? 'full';
        $processed = 0;

        // Process one employee at a time to avoid loading all records into memory
        foreach ($employeeIds as $employeeId) {
            $punches = Attendance::whereBetween('punch_time', [$startDate, $endDate])
                ->whereIn('status', ['Incomplete', 'NeedsReview'])
                ->where('employee_id', $employeeId)
                ->orderBy('punch_time')
                ->get();

            // Assign shift_date to all punches before processing
            foreach ($punches as $punch) {
                if (empty($punch->shift_date)) {
                    $this->attendanceTimeGroupService->getOrCreateShiftDate($punch, auth()->id() ?? 0);
                    $punch->save();
                }
            }

            // Handle odd-numbered punch days
            $punchDays = $punches->groupBy('shift_date');
            $unpairedPunches = collect();

            foreach ($punchDays as $shiftDate => $dayPunches) {
                if ($dayPunches->count() % 2 !== 0) {
                    $sortedPunches = $dayPunches->sortBy('punch_time');
                    $unpairedPunch = $this->findUnpairedPunch($sortedPunches);

                    if ($unpairedPunch) {
                        $unpairedPunch->status = 'NeedsReview';
                        $unpairedPunch->punch_type_id = null;
                        $unpairedPunch->punch_state = 'unknown';
                        $unpairedPunch->issue_notes = "Unpaired punch detected on {$shiftDate} - requires manual review";
                        $unpairedPunch->save();
                        $unpairedPunches->push($unpairedPunch);
                    }
                }
            }

            if ($unpairedPunches->isNotEmpty()) {
                $punches = $punches->reject(function ($punch) use ($unpairedPunches) {
                    return $unpairedPunches->pluck('id')->contains($punch->id);
                });
            }

            $punchEvaluations = [];

            if ($debugMode === 'ml') {
                $trainingDataCount = DB::table('attendances')
                    ->whereNotNull('punch_type_id')
                    ->where('status', 'Complete')
                    ->count();

                if ($trainingDataCount < 50) {
                    $this->heuristicService->assignPunchTypes($punches, $flexibility, $punchEvaluations);
                } else {
                    $this->mlService->assignPunchTypes($punches, $employeeId, $punchEvaluations);
                }
            } elseif ($debugMode === 'consensus') {
                $this->consensusService->processConsensus($punches, $employeeId, $flexibility, $punchEvaluations);
            } else {
                if ($debugMode === 'heuristic' || $debugMode === 'all') {
                    $this->heuristicService->assignPunchTypes($punches, $flexibility, $punchEvaluations);
                }

                $mlPendingPunches = $punches->whereNull('punch_type_id');
                if ($mlPendingPunches->isNotEmpty() && ($debugMode === 'all' && $companySetup->use_ml_for_punch_matching)) {
                    $this->mlService->assignPunchTypes($mlPendingPunches, $employeeId, $punchEvaluations);
                }
            }

            if ($debugMode !== 'consensus') {
                $this->finalizePunchTypes($punches, $punchEvaluations);
            }

            $processed++;
        }

        Log::info("[AttendanceTimeProcessorService] Completed - processed {$processed} employees for PayPeriod ID: {$payPeriod->id}");
    }

    private function finalizePunchTypes($punches, &$punchEvaluations): void
    {
        foreach ($punches as $punch) {
            $punchId = $punch->id;
            $evaluations = $punchEvaluations[$punchId] ?? [];

            if (empty($evaluations)) {
                $punch->status = 'NeedsReview';
                $punch->issue_notes = 'No confident Punch Type assigned.';
                $punch->save();

                continue;
            }

            $finalType = $this->selectFinalPunchType($evaluations);
            $finalState = $this->selectFinalPunchState($evaluations);

            if ($finalType) {
                $punch->punch_type_id = $finalType;
                $punch->punch_state = $finalState;
                $punch->status = 'Complete';
                $punch->issue_notes = 'Finalized Punch Type from multiple evaluations.';
                $punch->save();
            } else {
                $punch->status = 'NeedsReview';
                $punch->issue_notes = 'Could not determine Punch Type.';
                $punch->save();
            }
        }
    }

    private function selectFinalPunchType($evaluations): ?int
    {
        // Prioritize ML -> Heuristic
        if (isset($evaluations['ml']['punch_type_id'])) {
            return $evaluations['ml']['punch_type_id'];
        }

        if (isset($evaluations['heuristic']['punch_type_id'])) {
            return $evaluations['heuristic']['punch_type_id'];
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

        return 'unknown';
    }

    /**
     * Find the unpaired punch in an odd-numbered set of punches
     */
    private function findUnpairedPunch($sortedPunches)
    {
        $punches = $sortedPunches->values();
        $count = $punches->count();

        $bestCandidate = null;
        $smallestGapSum = PHP_INT_MAX;

        for ($i = 0; $i < $count; $i++) {
            $withoutPunch = $punches->reject(function ($punch, $index) use ($i) {
                return $index === $i;
            })->values();

            $gapSum = 0;
            for ($j = 0; $j < $withoutPunch->count() - 1; $j++) {
                $time1 = strtotime($withoutPunch[$j]->punch_time);
                $time2 = strtotime($withoutPunch[$j + 1]->punch_time);
                $gap = abs($time2 - $time1);
                $gapSum += $gap;
            }

            if ($gapSum < $smallestGapSum) {
                $smallestGapSum = $gapSum;
                $bestCandidate = $punches[$i];
            }
        }

        return $bestCandidate;
    }
}
