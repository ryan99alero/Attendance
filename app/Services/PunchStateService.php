<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PunchStateService
{
    /**
     * Determine the punch state (start/stop/unknown) based on the punch type.
     *
     * @param string $punchType
     * @param string|null $currentPunchState
     * @return string
     */
    public static function determinePunchState(string $punchType, ?string $currentPunchState = null): string
    {
        // Define the mapping for punch types to their default states
        $punchTypeStateMapping = [
            'start_time' => 'start',
            'stop_time' => 'stop',
            'clock_in' => 'start',
            'clock_out' => 'stop',
            'lunch_start' => 'stop',  // ✅ Lunch Start = Stopping work
            'lunch_stop' => 'start',  // ✅ Lunch Stop = Resuming work
            'unclassified' => 'unknown',
        ];

        return $punchTypeStateMapping[$punchType] ?? 'unknown';
    }

    /**
     * Validate whether a given punch state is valid for the provided punch type.
     *
     * @param string $punchType
     * @param string $punchState
     * @return bool
     */
    public static function isValidPunchState(string $punchType, string $punchState): bool
    {
        $validStates = ['start', 'stop'];

        // Punch types that should have either 'start' or 'stop'
        $punchTypesWithStates = ['start_time', 'stop_time', 'clock_in', 'clock_out', 'lunch_start', 'lunch_stop'];

        // If punch type supports start/stop, ensure punchState is valid
        if (in_array($punchType, $punchTypesWithStates, true)) {
            return in_array($punchState, $validStates, true);
        }

        // All other punch types should be 'unknown'
        return $punchState === 'unknown';
    }
}
