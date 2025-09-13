<?php

namespace App\Services\Shift;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShiftSchedulePunchTypeAssignmentService
{
    protected ShiftScheduleService $shiftScheduleService;

    public function __construct(ShiftScheduleService $shiftScheduleService)
    {
        $this->shiftScheduleService = $shiftScheduleService;
        Log::info("[Shift] Initialized ShiftSchedulePunchTypeAssignmentService.");
    }

    public function assignPunchTypes($punches, $flexibility, &$punchEvaluations): void
    {
        Log::info("➡️ [Heuristic] Entering assignPunchTypes");
        foreach ($punches->groupBy('employee_id') as $employeeId => $employeePunches) {
            $schedule = $this->shiftScheduleService->getShiftScheduleForEmployee($employeeId);

            if (!$schedule) {
                Log::warning("❌ [Shift] No shift schedule found for Employee ID: {$employeeId}");
                foreach ($employeePunches as $punch) {
                    $punchEvaluations[$punch->id]['shift'] = [
                        'punch_type_id' => null,
                        'punch_state' => 'unknown',
                        'source' => 'Shift Schedule (No Match)'
                    ];
                }
                continue;
            }

            Log::info("✅ [Shift] Using Shift Schedule ID: {$schedule->id} for Employee ID: {$employeeId}");

            // Process Punch Assignments
            $this->processPunchAssignments($employeePunches, $schedule, $flexibility, $punchEvaluations);
        }
    }

    private function processPunchAssignments($punches, $schedule, $flexibility, &$punchEvaluations): void
    {
        Log::info("➡️ [Heuristic] Entering processPunchAssignments");
        $punchesByDay = $punches->groupBy(fn($punch) => Carbon::parse($punch->punch_time)->format('Y-m-d'));

        foreach ($punchesByDay as $day => $dailyPunches) {
            Log::info("🔍 [Shift] Processing punches for Date: {$day}, Employee ID: {$dailyPunches->first()->employee_id}, Count: " . $dailyPunches->count());

            $dailyPunches = $dailyPunches->sortBy('punch_time')->values();

            // Store predictions for later review, even if punch count is odd
            $this->assignScheduledPunchTypes($dailyPunches, $schedule, $flexibility, $punchEvaluations);
        }
    }

    private function assignScheduledPunchTypes($punches, $schedule, $flexibility, &$punchEvaluations): void
    {
        Log::info("➡️ [Heuristic] Entering assignScheduledPunchTypes for {$punches->count()} punches");
        
        $punchCount = $punches->count();
        $firstPunch = $punches->first();
        $lastPunch = $punches->last();

        // Always assign first and last punches as Clock In/Out
        $this->storePunchPrediction($firstPunch, 'Clock In', $punchEvaluations);
        $this->storePunchPrediction($lastPunch, 'Clock Out', $punchEvaluations);
        Log::info("✅ [Shift] Assigned Clock In (Punch ID: {$firstPunch->id}) and Clock Out (Punch ID: {$lastPunch->id}).");

        // Handle different punch count scenarios
        if ($punchCount == 2) {
            // 2 punches: Clock In, Clock Out (working through lunch)
            Log::info("📝 [Shift] 2-punch scenario: Employee worked through lunch");
        } elseif ($punchCount == 4) {
            // 4 punches: Clock In, Lunch Start, Lunch Stop, Clock Out (restore original logic)
            $middlePunches = $punches->slice(1, -1);
            $this->assignLunchPunchTypesOriginal($middlePunches, $punchEvaluations);
        } elseif ($punchCount >= 6) {
            // 6+ punches: Clock In, [breaks/lunch], Clock Out
            $middlePunches = $punches->slice(1, -1);
            $this->assignLunchAndBreakPunchTypes($middlePunches, $schedule, $flexibility, $punchEvaluations);
        }
    }

    private function assignLunchPunchTypesOriginal($punches, &$punchEvaluations): void
    {
        Log::info("➡️ [Heuristic] Entering assignLunchPunchTypesOriginal for 4-punch scenario");
        
        // For 4-punch days, assign middle punches as Lunch Start/Stop chronologically (original logic)
        if ($punches->count() == 2) {
            $first = $punches->first();
            $second = $punches->last();
            
            Log::info("✅ [Shift] Assigning Lunch Start (Punch ID: {$first->id}) and Lunch Stop (Punch ID: {$second->id}) chronologically");
            $this->storePunchPrediction($first, 'Lunch Start', $punchEvaluations);
            $this->storePunchPrediction($second, 'Lunch Stop', $punchEvaluations);
        }
    }

    private function assignLunchAndBreakPunchTypes($punches, $schedule, $flexibility, &$punchEvaluations): void
    {
        Log::info("➡️ [Heuristic] Entering assignLunchAndBreakPunchTypes for {$punches->count()} middle punches");
        
        $lunchStart = Carbon::parse($schedule->lunch_start_time);
        $lunchEnd = Carbon::parse($schedule->lunch_stop_time); 
        $expectedLunchDuration = $schedule->lunch_duration; // in minutes
        
        // Find the best lunch pair based on schedule timing and duration
        $bestLunchPair = $this->findBestLunchPair($punches, $lunchStart, $lunchEnd, $expectedLunchDuration, $flexibility);
        
        if ($bestLunchPair) {
            Log::info("✅ [Shift] Found best lunch pair - Start: {$bestLunchPair['start']->id}, Stop: {$bestLunchPair['stop']->id}");
            $this->storePunchPrediction($bestLunchPair['start'], 'Lunch Start', $punchEvaluations);
            $this->storePunchPrediction($bestLunchPair['stop'], 'Lunch Stop', $punchEvaluations);
            
            // Remove lunch punches from the list and assign remaining as breaks
            $remainingPunches = $punches->reject(function ($punch) use ($bestLunchPair) {
                return $punch->id === $bestLunchPair['start']->id || $punch->id === $bestLunchPair['stop']->id;
            })->values();
            
            $this->assignBreakPunchTypes($remainingPunches, $punchEvaluations);
        } else {
            Log::warning("⚠️ [Shift] Could not find optimal lunch pair, assigning all middle punches as breaks");
            $this->assignBreakPunchTypes($punches, $punchEvaluations);
        }
    }

    private function findBestLunchPair($punches, $lunchStart, $lunchEnd, $expectedDuration, $flexibility): ?array
    {
        try {
            Log::info("🔍 [Shift] Finding best lunch pair from {$punches->count()} punches");
            
            $bestPair = null;
            $bestScore = -1;
            $punchCount = $punches->count();
            
            Log::info("🔍 [Shift] Punch count: {$punchCount}");
            
            // Use Collection methods instead of array conversion
            $punchArray = $punches->values(); // Keep as Collection, just reset keys
            
            // Try all possible pairs of punches
            for ($i = 0; $i < $punchCount - 1; $i += 2) { // Step by 2 to maintain start/stop pairing
                if ($i + 1 >= $punchCount) {
                    Log::info("🔍 [Shift] Breaking at index {$i}, would exceed count");
                    break;
                }
                
                $startPunch = $punchArray->get($i);
                $stopPunch = $punchArray->get($i + 1);
                
                Log::info("🔍 [Shift] Evaluating pair: {$startPunch->id} -> {$stopPunch->id}");
                
                $score = $this->scoreLunchPair($startPunch, $stopPunch, $lunchStart, $lunchEnd, $expectedDuration, $flexibility);
                
                Log::info("🎯 [Shift] Lunch pair score for punches {$startPunch->id} -> {$stopPunch->id}: {$score}");
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestPair = ['start' => $startPunch, 'stop' => $stopPunch, 'score' => $score];
                    Log::info("🏆 [Shift] New best pair found with score: {$score}");
                }
            }
            
            // Only return a pair if it has a reasonable score (at least some criteria met)
            $result = ($bestScore > 0) ? $bestPair : null;
            Log::info("🔍 [Shift] Best lunch pair result: " . ($result ? "Found with score {$bestScore}" : "None found"));
            
            return $result;
        } catch (\Exception $e) {
            Log::error("❌ [Shift] Error in findBestLunchPair: " . $e->getMessage());
            Log::error("❌ [Shift] Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }

    private function scoreLunchPair($startPunch, $stopPunch, $lunchStart, $lunchEnd, $expectedDuration, $flexibility): int
    {
        try {
            $score = 0;
            
            Log::info("  🎯 [Shift] Scoring pair: {$startPunch->punch_time} -> {$stopPunch->punch_time}");
            
            // Check if start time is close to scheduled lunch start
            if ($this->isWithinFlexibility($startPunch->punch_time, $lunchStart, $flexibility)) {
                $score += 10;
                Log::info("  ✅ Start time matches scheduled lunch start (+10)");
            }
            
            // Check if end time is close to scheduled lunch end  
            if ($this->isWithinFlexibility($stopPunch->punch_time, $lunchEnd, $flexibility)) {
                $score += 10;
                Log::info("  ✅ End time matches scheduled lunch end (+10)");
            }
            
            // Check if duration matches expected lunch duration (within 15 minutes tolerance)
            $actualDuration = Carbon::parse($startPunch->punch_time)->diffInMinutes(Carbon::parse($stopPunch->punch_time));
            $durationDiff = abs($actualDuration - $expectedDuration);
            
            Log::info("  📏 Duration analysis: actual={$actualDuration}min, expected={$expectedDuration}min, diff={$durationDiff}min");
            
            if ($durationDiff <= 5) {
                $score += 15; // Perfect duration match
                Log::info("  ✅ Perfect duration match: {$actualDuration}min vs expected {$expectedDuration}min (+15)");
            } elseif ($durationDiff <= 15) {
                $score += 5; // Good duration match
                Log::info("  ✅ Good duration match: {$actualDuration}min vs expected {$expectedDuration}min (+5)");
            } else {
                Log::info("  ❌ Poor duration match: {$actualDuration}min vs expected {$expectedDuration}min (0)");
            }
            
            // Prefer pairs that fall within typical lunch hours (11:00 AM - 2:00 PM)
            $startHour = Carbon::parse($startPunch->punch_time)->hour;
            if ($startHour >= 11 && $startHour <= 14) {
                $score += 5;
                Log::info("  ✅ Within typical lunch hours ({$startHour}:xx) (+5)");
            } else {
                Log::info("  ❌ Outside typical lunch hours ({$startHour}:xx) (0)");
            }
            
            Log::info("  📊 Total score for pair: {$score}");
            return $score;
        } catch (\Exception $e) {
            Log::error("❌ [Shift] Error in scoreLunchPair: " . $e->getMessage());
            return 0;
        }
    }

    private function assignBreakPunchTypes($punches, &$punchEvaluations): void
    {
        Log::info("🍃 [Shift] Assigning {$punches->count()} punches as breaks");
        
        // Use Collection methods instead of array conversion
        $punchArray = $punches->values(); // Keep as Collection, just reset keys
        $punchCount = $punchArray->count();
        
        // Assign remaining punches as Break Start/Break End pairs chronologically
        for ($i = 0; $i < $punchCount; $i += 2) {
            if ($i >= $punchCount) break;
            
            $startPunch = $punchArray->get($i);
            $this->storePunchPrediction($startPunch, 'Break Start', $punchEvaluations);
            Log::info("✅ [Shift] Assigned Break Start to Punch ID: {$startPunch->id}");
            
            if ($i + 1 < $punchCount) {
                $endPunch = $punchArray->get($i + 1);
                $this->storePunchPrediction($endPunch, 'Break End', $punchEvaluations);
                Log::info("✅ [Shift] Assigned Break End to Punch ID: {$endPunch->id}");
            }
        }
    }

    private function isWithinFlexibility($punchTime, $scheduledTime, $flexibility): bool
    {
        $punchTimeOnly = Carbon::parse($punchTime)->format('H:i:s');
        $scheduledTimeOnly = Carbon::parse($scheduledTime)->format('H:i:s');
        
        $punchCarbon = Carbon::parse($punchTimeOnly);
        $scheduledCarbon = Carbon::parse($scheduledTimeOnly);
        
        return $punchCarbon->between(
            $scheduledCarbon->copy()->subMinutes($flexibility), 
            $scheduledCarbon->copy()->addMinutes($flexibility)
        );
    }

    private function storePunchPrediction($punch, $type, &$punchEvaluations): void
    {
        Log::info("➡️ [Heuristic] Entering storePunchPrediction");
        $punchTypeId = $this->getPunchTypeId($type);

        if (!$punchTypeId) {
            Log::warning("⚠️ [Shift] Punch Type ID not found for: {$type}, Punch ID: {$punch->id}");
            return;
        }

        Log::info("🛠 [Shift] Predicting {$type} (ID: {$punchTypeId}) for Punch ID: {$punch->id}");

        $punchEvaluations[$punch->id]['shift'] = [
            'punch_type_id' => $punchTypeId,
            'punch_state' => $this->determinePunchState($punchTypeId),
            'source' => 'Shift Schedule'
        ];
    }

    private function determinePunchState(int $punchTypeId): string
    {
        Log::info("➡️ [Heuristic] Entering determinePunchState");
        $startTypes = ['Clock In', 'Lunch Start', 'Break Start', 'Shift Start', 'Manual Start'];
        $stopTypes = ['Clock Out', 'Lunch Stop', 'Break End', 'Shift Stop', 'Manual Stop'];

        $punchTypeName = \DB::table('punch_types')->where('id', $punchTypeId)->value('name');

        if (in_array($punchTypeName, $startTypes)) {
            return 'start';
        } elseif (in_array($punchTypeName, $stopTypes)) {
            return 'stop';
        }

        return 'unknown';
    }

    private function getPunchTypeId(string $type): ?int
    {
        Log::info("➡️ [Heuristic] Entering getPunchTypeId");
        return \DB::table('punch_types')->where('name', $type)->value('id');
    }
}
