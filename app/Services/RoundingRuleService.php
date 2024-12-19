<?php

namespace App\Services;

use App\Models\RoundingRule;

class RoundingRuleService
{
    /**
     * Retrieve all rounding rules for a specific group.
     *
     * @param int $roundGroupId
     * @return \Illuminate\Support\Collection
     */
    public function getRulesForGroup(int $roundGroupId)
    {
        return RoundingRule::where('round_group_id', $roundGroupId)->orderBy('minute_min')->get();
    }

    /**
     * Find a rounding rule for specific minutes and group.
     *
     * @param int $minutes
     * @param int $roundGroupId
     * @return RoundingRule|null
     */
    public function findRule(int $minutes, int $roundGroupId): ?RoundingRule
    {
        return RoundingRule::where('round_group_id', $roundGroupId)
            ->where('minute_min', '<=', $minutes)
            ->where('minute_max', '>=', $minutes)
            ->first();
    }

    /**
     * Apply a rounding rule to elapsed minutes based on the group.
     *
     * @param int $minutes
     * @param int $roundGroupId
     * @return int
     */
    public function applyRoundingRule(int $minutes, int $roundGroupId): int
    {
        $rule = $this->findRule($minutes, $roundGroupId);

        return $rule ? $rule->new_minute : $minutes; // Default to original minutes if no rule is found
    }

    /**
     * Validate that the rounding rules for a group are consistent.
     *
     * @param int $roundGroupId
     * @return bool
     */
    public function validateRules(int $roundGroupId): bool
    {
        $rules = $this->getRulesForGroup($roundGroupId);

        foreach ($rules as $i => $rule) {
            if (isset($rules[$i + 1]) && $rules[$i + 1]->minute_min <= $rule->minute_max) {
                // Overlapping ranges detected
                return false;
            }
        }

        return true;
    }
}
