<?php

namespace App\Services;

use DateTime;
use Log;
use App\Models\RoundGroup;
use App\Models\RoundingRule;

class RoundingRuleService
{
    /**
     * Calculate rounded punch time.
     *
     * @param DateTime $originalTime
     * @param int $roundGroupId
     * @return DateTime
     */
    public function getRoundedTime(DateTime $originalTime, int $roundGroupId): DateTime
    {
        Log::info("Original Time: {$originalTime->format('Y-m-d H:i:s')}, Round Group ID: {$roundGroupId}");

        $roundingRules = RoundingRule::where('round_group_id', $roundGroupId)->get();

        if ($roundingRules->isEmpty()) {
            Log::info("No rounding rules found for Round Group ID: {$roundGroupId}");
            return $originalTime;
        }

        $minute = (int)$originalTime->format('i'); // Extract the minutes from the time

        foreach ($roundingRules as $rule) {
            if ($minute >= $rule->minute_min && $minute <= $rule->minute_max) {
                $roundedMinute = $rule->new_minute;
                $roundedTime = clone $originalTime;
                $roundedTime->setTime(
                    (int)$originalTime->format('H'),
                    $roundedMinute,
                    0
                );
                Log::info("Rounded Time: {$roundedTime->format('Y-m-d H:i:s')}");
                return $roundedTime;
            }
        }

        Log::info("No matching rounding rule for Minute: {$minute}");
        return $originalTime;
    }
}
