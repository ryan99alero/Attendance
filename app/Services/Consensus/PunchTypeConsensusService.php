<?php

namespace App\Services\Consensus;

use App\Services\Heuristic\HeuristicPunchTypeAssignmentService;
use App\Services\ML\MLPunchTypePredictorService;
use Illuminate\Support\Facades\DB;

class PunchTypeConsensusService
{
    protected HeuristicPunchTypeAssignmentService $heuristicService;

    protected MLPunchTypePredictorService $mlService;

    protected array $punchTypeNameCache = [];

    public function __construct(
        HeuristicPunchTypeAssignmentService $heuristicService,
        MLPunchTypePredictorService $mlService
    ) {
        $this->heuristicService = $heuristicService;
        $this->mlService = $mlService;
        $this->cachePunchTypeNames();
    }

    protected function cachePunchTypeNames(): void
    {
        $punchTypes = DB::table('punch_types')->get();
        foreach ($punchTypes as $punchType) {
            $this->punchTypeNameCache[$punchType->id] = $punchType->name;
        }
    }

    /**
     * Run consensus checking on all punches
     */
    public function processConsensus($punches, $employeeId, $flexibility, &$punchEvaluations): void
    {
        DB::disableQueryLog();

        $this->heuristicService->assignPunchTypes($punches, $flexibility, $punchEvaluations);
        $this->mlService->assignPunchTypes($punches, $employeeId, $punchEvaluations);

        foreach ($punches as $punch) {
            $consensusResult = $this->analyzeConsensus($punch->id, $punchEvaluations);
            $punchEvaluations[$punch->id]['consensus'] = $consensusResult;

            if ($consensusResult['has_disagreement']) {
                $punch->status = 'Discrepancy';
                $punch->issue_notes = $consensusResult['disagreement_details'];
                $punch->save();
            } else {
                $punch->punch_type_id = $consensusResult['agreed_punch_type_id'];
                $punch->punch_state = $consensusResult['agreed_punch_state'];
                $punch->status = 'Complete';
                $punch->issue_notes = 'Consensus achieved: '.$consensusResult['consensus_method'];
                $punch->save();
            }
        }
    }

    /**
     * Analyze consensus between engine results for a single punch
     */
    private function analyzeConsensus(int $punchId, array $punchEvaluations): array
    {
        $heuristicResult = $punchEvaluations[$punchId]['heuristic'] ?? null;
        $mlResult = $punchEvaluations[$punchId]['ml'] ?? null;

        $result = [
            'has_disagreement' => false,
            'disagreement_details' => null,
            'disagreement_summary' => null,
            'agreed_punch_type_id' => null,
            'agreed_punch_state' => null,
            'consensus_method' => null,
        ];

        if (! $heuristicResult || ! $mlResult) {
            $result['has_disagreement'] = true;
            $result['disagreement_summary'] = 'Missing engine result';
            $result['disagreement_details'] = json_encode([
                'issue' => 'incomplete_evaluation',
                'heuristic_available' => ! is_null($heuristicResult),
                'ml_available' => ! is_null($mlResult),
            ]);

            return $result;
        }

        $heuristicType = $heuristicResult['punch_type_id'];
        $heuristicState = $heuristicResult['punch_state'];
        $mlType = $mlResult['punch_type_id'];
        $mlState = $mlResult['punch_state'];

        if ($heuristicType === $mlType && $heuristicState === $mlState) {
            $result['agreed_punch_type_id'] = $heuristicType;
            $result['agreed_punch_state'] = $heuristicState;
            $result['consensus_method'] = 'Perfect agreement between engines';

            return $result;
        }

        if ($heuristicType === $mlType && $heuristicState !== $mlState) {
            $result['agreed_punch_type_id'] = $heuristicType;
            $result['agreed_punch_state'] = $heuristicState;
            $result['consensus_method'] = 'Type agreement, heuristic state preferred';

            return $result;
        }

        $result['has_disagreement'] = true;
        $result['disagreement_summary'] = $this->getTypeNames($heuristicType, $mlType);
        $result['disagreement_details'] = json_encode([
            'disagreement_type' => 'engine_mismatch',
            'heuristic_type' => $heuristicType,
            'ml_type' => $mlType,
        ]);

        return $result;
    }

    private function getTypeNames(?int $heuristicType, ?int $mlType): string
    {
        $heuristicName = $this->getPunchTypeName($heuristicType) ?? 'Unknown';
        $mlName = $this->getPunchTypeName($mlType) ?? 'Unknown';

        return "Heuristic: {$heuristicName} vs ML: {$mlName}";
    }

    private function getPunchTypeName(?int $punchTypeId): ?string
    {
        if (! $punchTypeId) {
            return null;
        }

        return $this->punchTypeNameCache[$punchTypeId] ?? null;
    }
}
