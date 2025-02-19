<?php

namespace App\Services\Heuristic;

use App\Models\Attendance;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HeuristicPunchTypeAssignmentService
{
    public function __construct()
    {
        Log::info("Initialized HeuristicPunchTypeAssignmentService.");
    }

    public function assignPunchTypes($punches, $flexibility): void
    {
        Log::info("🔍 [Heuristic] Processing Punch Assignments...");

        foreach ($punches as $punch) {
            // Heuristic-based Punch Type Assignment
            $assignedType = $this->determineHeuristicPunchType($punch);

            if ($assignedType) {
                $punch->punch_type_id = $assignedType;
                $punch->status = 'Complete';
                $punch->issue_notes = "Assigned by Heuristic Model";

                if ($punch->save()) {
                    Log::info("✅ [Heuristic] Assigned Punch ID: {$punch->id} -> Type: {$assignedType}");
                } else {
                    Log::error("❌ [Heuristic] Failed to save Punch ID: {$punch->id}");
                }
            } else {
                Log::warning("⚠️ [Heuristic] No suitable punch type found for Punch ID: {$punch->id}");
            }
        }
    }

    private function determineHeuristicPunchType($punch): ?int
    {
        // Example: Assign Punch Type ID based on time range
        $punchTime = Carbon::parse($punch->punch_time);
        $hour = $punchTime->hour;

        if ($hour >= 6 && $hour < 9) {
            return $this->getPunchTypeId('Clock In');
        } elseif ($hour >= 11 && $hour < 13) {
            return $this->getPunchTypeId('Lunch Start');
        } elseif ($hour >= 12 && $hour < 14) {
            return $this->getPunchTypeId('Lunch Stop');
        } elseif ($hour >= 15 && $hour < 19) {
            return $this->getPunchTypeId('Clock Out');
        }

        return null;
    }

    private function getPunchTypeId(string $type): ?int
    {
        return \DB::table('punch_types')->where('name', $type)->value('id');
    }
}
