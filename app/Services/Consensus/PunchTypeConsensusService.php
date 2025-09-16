<?php

namespace App\Services\Consensus;

use App\Services\Heuristic\HeuristicPunchTypeAssignmentService;
use App\Services\ML\MLPunchTypePredictorService;
use Illuminate\Support\Facades\Log;

class PunchTypeConsensusService
{
    protected HeuristicPunchTypeAssignmentService $heuristicService;
    protected MLPunchTypePredictorService $mlService;

    public function __construct(
        HeuristicPunchTypeAssignmentService $heuristicService,
        MLPunchTypePredictorService $mlService
    ) {
        $this->heuristicService = $heuristicService;
        $this->mlService = $mlService;
        Log::info("[Consensus] Initialized PunchTypeConsensusService.");
    }

    /**
     * Run consensus checking on all punches by having both engines evaluate them
     */
    public function processConsensus($punches, $employeeId, $flexibility, &$punchEvaluations): void
    {
        Log::info("[Consensus] Starting consensus processing for Employee ID: {$employeeId}, Punch Count: " . $punches->count());

        // Run both engines on ALL punches
        $this->heuristicService->assignPunchTypes($punches, $flexibility, $punchEvaluations);
        $this->mlService->assignPunchTypes($punches, $employeeId, $punchEvaluations);

        // Apply consensus analysis to each punch
        foreach ($punches as $punch) {
            $consensusResult = $this->analyzeConsensus($punch->id, $punchEvaluations);
            $punchEvaluations[$punch->id]['consensus'] = $consensusResult;

            // Update punch status based on consensus
            if ($consensusResult['has_disagreement']) {
                $punch->status = 'Discrepancy';
                $punch->issue_notes = $consensusResult['disagreement_details'];
                $punch->save();

                Log::warning("[Consensus] Disagreement detected for Punch ID: {$punch->id} - {$consensusResult['disagreement_summary']}");
            } else {
                // Engines agree or one has a confident result
                $punch->punch_type_id = $consensusResult['agreed_punch_type_id'];
                $punch->punch_state = $consensusResult['agreed_punch_state'];
                $punch->status = 'Complete';
                $punch->issue_notes = "Consensus achieved: " . $consensusResult['consensus_method'];
                $punch->save();

                Log::info("[Consensus] Consensus achieved for Punch ID: {$punch->id} -> Type: {$consensusResult['agreed_punch_type_id']} ({$consensusResult['consensus_method']})");
            }
        }

        Log::info("[Consensus] Completed consensus processing for Employee ID: {$employeeId}");
    }

    /**
     * Analyze consensus between engine results for a single punch
     */
    private function analyzeConsensus(int $punchId, array $punchEvaluations): array
    {
        $heuristicResult = $punchEvaluations[$punchId]['heuristic'] ?? null;
        $mlResult = $punchEvaluations[$punchId]['ml'] ?? null;

        // Initialize result structure
        $result = [
            'has_disagreement' => false,
            'disagreement_details' => null,
            'disagreement_summary' => null,
            'agreed_punch_type_id' => null,
            'agreed_punch_state' => null,
            'consensus_method' => null
        ];

        // Check if both engines provided results
        if (!$heuristicResult || !$mlResult) {
            $result['has_disagreement'] = true;
            $result['disagreement_summary'] = 'Missing engine result';
            $result['disagreement_details'] = json_encode([
                'issue' => 'incomplete_evaluation',
                'heuristic_available' => !is_null($heuristicResult),
                'ml_available' => !is_null($mlResult),
                'heuristic_result' => $heuristicResult,
                'ml_result' => $mlResult
            ]);
            return $result;
        }

        $heuristicType = $heuristicResult['punch_type_id'];
        $heuristicState = $heuristicResult['punch_state'];
        $mlType = $mlResult['punch_type_id'];
        $mlState = $mlResult['punch_state'];

        // Perfect agreement - both engines agree on type and state
        if ($heuristicType === $mlType && $heuristicState === $mlState) {
            $result['agreed_punch_type_id'] = $heuristicType;
            $result['agreed_punch_state'] = $heuristicState;
            $result['consensus_method'] = 'Perfect agreement between engines';
            return $result;
        }

        // Partial agreement - same type, different state
        if ($heuristicType === $mlType && $heuristicState !== $mlState) {
            // Use heuristic state as tiebreaker (can be configured)
            $result['agreed_punch_type_id'] = $heuristicType;
            $result['agreed_punch_state'] = $heuristicState;
            $result['consensus_method'] = 'Type agreement, heuristic state preferred';

            Log::info("[Consensus] Partial agreement for Punch ID: {$punchId} - Type: {$heuristicType}, States differ: H={$heuristicState}, ML={$mlState}");
            return $result;
        }

        // Complete disagreement - different types
        $result['has_disagreement'] = true;
        $result['disagreement_summary'] = $this->getTypeNames($heuristicType, $mlType);
        $result['disagreement_details'] = json_encode([
            'disagreement_type' => 'engine_mismatch',
            'heuristic_result' => [
                'punch_type_id' => $heuristicType,
                'punch_type_name' => $this->getPunchTypeName($heuristicType),
                'punch_state' => $heuristicState,
                'source' => $heuristicResult['source'] ?? 'Heuristic Engine'
            ],
            'ml_result' => [
                'punch_type_id' => $mlType,
                'punch_type_name' => $this->getPunchTypeName($mlType),
                'punch_state' => $mlState,
                'source' => $mlResult['source'] ?? 'ML Engine'
            ],
            'requires_review' => true
        ]);

        return $result;
    }

    /**
     * Get human-readable disagreement summary
     */
    private function getTypeNames(?int $heuristicType, ?int $mlType): string
    {
        $heuristicName = $this->getPunchTypeName($heuristicType) ?? 'Unknown';
        $mlName = $this->getPunchTypeName($mlType) ?? 'Unknown';

        return "Heuristic: {$heuristicName} vs ML: {$mlName}";
    }

    /**
     * Get punch type name from ID
     */
    private function getPunchTypeName(?int $punchTypeId): ?string
    {
        if (!$punchTypeId) {
            return null;
        }

        return \DB::table('punch_types')->where('id', $punchTypeId)->value('name');
    }
}