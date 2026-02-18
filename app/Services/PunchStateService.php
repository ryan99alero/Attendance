<?php

namespace App\Services;

use App\Models\PunchType;

class PunchStateService
{
    /**
     * Determine the punch state (start/stop/unknown) based on the punch type.
     *
     * Now uses the punch_direction column directly from the PunchType model
     * instead of a hardcoded mapping.
     *
     * @param  int|string|PunchType  $punchType  PunchType model, ID, or legacy name
     * @param  string|null  $currentPunchState  Unused, kept for backwards compatibility
     * @return string 'start', 'stop', or 'unknown'
     */
    public static function determinePunchState(int|string|PunchType $punchType, ?string $currentPunchState = null): string
    {
        // If already a PunchType model, use it directly
        if ($punchType instanceof PunchType) {
            return $punchType->punch_direction ?? 'unknown';
        }

        // If it's an integer, look up by ID
        if (is_int($punchType)) {
            $model = PunchType::find($punchType);

            return $model?->punch_direction ?? 'unknown';
        }

        // Legacy support: if it's a string, try to look up by name
        $model = PunchType::where('name', $punchType)->first();

        return $model?->punch_direction ?? 'unknown';
    }

    /**
     * Get the punch state directly from a PunchType model.
     */
    public static function getStateFromPunchType(PunchType $punchType): string
    {
        return $punchType->punch_direction ?? 'unknown';
    }
}
