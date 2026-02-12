<?php

namespace Database\Seeders;

use App\Models\OvertimeRule;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class OvertimeRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder creates default overtime rules for 1st and 2nd shifts:
     *
     * 1st Shift (8hr/day, Mon-Fri):
     * - Weekly 40hr threshold (1.5x overtime)
     * - Saturday = 1.5x overtime
     * - Sunday = 2.0x double-time (requires Saturday worked)
     * - 7th consecutive day = 2.0x double-time
     *
     * 2nd Shift (9hr/day, Mon-Thu):
     * - Weekly 36hr threshold (1.5x overtime)
     * - Friday = 1.5x overtime
     * - Saturday = 2.0x double-time (requires Friday worked)
     * - 6th consecutive day = 2.0x double-time
     */
    public function run(): void
    {
        // Find or create shifts
        $firstShift = Shift::firstOrCreate(
            ['shift_name' => '1st Shift'],
            [
                'start_time' => '06:00:00',
                'end_time' => '14:00:00',
                'base_hours_per_period' => 80,
            ]
        );

        $secondShift = Shift::firstOrCreate(
            ['shift_name' => '2nd Shift'],
            [
                'start_time' => '14:00:00',
                'end_time' => '23:00:00',
                'base_hours_per_period' => 72,
            ]
        );

        // =====================================================
        // 1st Shift Rules
        // =====================================================

        // 1st Shift - Weekly 40hr threshold
        OvertimeRule::updateOrCreate(
            [
                'rule_name' => '1st Shift Weekly Overtime',
                'shift_id' => $firstShift->id,
            ],
            [
                'rule_type' => OvertimeRule::TYPE_WEEKLY_THRESHOLD,
                'hours_threshold' => 40,
                'multiplier' => 1.5,
                'double_time_multiplier' => 2.0,
                'eligible_pay_types' => [OvertimeRule::PAY_TYPE_HOURLY, OvertimeRule::PAY_TYPE_CONTRACT],
                'priority' => 100,
                'is_active' => true,
                'description' => '1st Shift: Overtime (1.5x) for hours worked over 40 per week.',
            ]
        );

        // 1st Shift - Saturday = 1.5x overtime
        OvertimeRule::updateOrCreate(
            [
                'rule_name' => '1st Shift Saturday Overtime',
                'shift_id' => $firstShift->id,
            ],
            [
                'rule_type' => OvertimeRule::TYPE_WEEKEND_DAY,
                'hours_threshold' => 0,
                'multiplier' => 1.5,
                'double_time_multiplier' => 1.5, // Not used for this rule
                'applies_to_days' => [OvertimeRule::SATURDAY],
                'eligible_pay_types' => [OvertimeRule::PAY_TYPE_HOURLY, OvertimeRule::PAY_TYPE_CONTRACT],
                'requires_prior_day_worked' => false,
                'priority' => 20,
                'is_active' => true,
                'description' => '1st Shift: All hours worked on Saturday are overtime (1.5x).',
            ]
        );

        // 1st Shift - Sunday = 2.0x double-time (requires Saturday worked)
        OvertimeRule::updateOrCreate(
            [
                'rule_name' => '1st Shift Sunday Double-Time',
                'shift_id' => $firstShift->id,
            ],
            [
                'rule_type' => OvertimeRule::TYPE_WEEKEND_DAY,
                'hours_threshold' => 0,
                'multiplier' => 2.0,
                'double_time_multiplier' => 2.0,
                'applies_to_days' => [OvertimeRule::SUNDAY],
                'eligible_pay_types' => [OvertimeRule::PAY_TYPE_HOURLY, OvertimeRule::PAY_TYPE_CONTRACT],
                'requires_prior_day_worked' => true, // Must work Saturday for Sunday DT
                'priority' => 10,
                'is_active' => true,
                'description' => '1st Shift: Sunday is double-time (2.0x) only if Saturday was also worked.',
            ]
        );

        // 1st Shift - 7th consecutive day = 2.0x double-time
        OvertimeRule::updateOrCreate(
            [
                'rule_name' => '1st Shift 7th Consecutive Day',
                'shift_id' => $firstShift->id,
            ],
            [
                'rule_type' => OvertimeRule::TYPE_CONSECUTIVE_DAY,
                'hours_threshold' => 0,
                'multiplier' => 2.0,
                'double_time_multiplier' => 2.0,
                'consecutive_days_threshold' => 7,
                'eligible_pay_types' => [OvertimeRule::PAY_TYPE_HOURLY, OvertimeRule::PAY_TYPE_CONTRACT],
                'only_applies_to_final_day' => false, // Applies to 7th day and beyond
                'priority' => 5,
                'is_active' => true,
                'description' => '1st Shift: 7th consecutive day and beyond is double-time (2.0x).',
            ]
        );

        // =====================================================
        // 2nd Shift Rules
        // =====================================================

        // 2nd Shift - Weekly 36hr threshold
        OvertimeRule::updateOrCreate(
            [
                'rule_name' => '2nd Shift Weekly Overtime',
                'shift_id' => $secondShift->id,
            ],
            [
                'rule_type' => OvertimeRule::TYPE_WEEKLY_THRESHOLD,
                'hours_threshold' => 36,
                'multiplier' => 1.5,
                'double_time_multiplier' => 2.0,
                'eligible_pay_types' => [OvertimeRule::PAY_TYPE_HOURLY, OvertimeRule::PAY_TYPE_CONTRACT],
                'priority' => 100,
                'is_active' => true,
                'description' => '2nd Shift: Overtime (1.5x) for hours worked over 36 per week.',
            ]
        );

        // 2nd Shift - Friday = 1.5x overtime
        OvertimeRule::updateOrCreate(
            [
                'rule_name' => '2nd Shift Friday Overtime',
                'shift_id' => $secondShift->id,
            ],
            [
                'rule_type' => OvertimeRule::TYPE_WEEKEND_DAY,
                'hours_threshold' => 0,
                'multiplier' => 1.5,
                'double_time_multiplier' => 1.5,
                'applies_to_days' => [OvertimeRule::FRIDAY],
                'eligible_pay_types' => [OvertimeRule::PAY_TYPE_HOURLY, OvertimeRule::PAY_TYPE_CONTRACT],
                'requires_prior_day_worked' => false,
                'priority' => 20,
                'is_active' => true,
                'description' => '2nd Shift: All hours worked on Friday are overtime (1.5x).',
            ]
        );

        // 2nd Shift - Saturday = 2.0x double-time (requires Friday worked)
        OvertimeRule::updateOrCreate(
            [
                'rule_name' => '2nd Shift Saturday Double-Time',
                'shift_id' => $secondShift->id,
            ],
            [
                'rule_type' => OvertimeRule::TYPE_WEEKEND_DAY,
                'hours_threshold' => 0,
                'multiplier' => 2.0,
                'double_time_multiplier' => 2.0,
                'applies_to_days' => [OvertimeRule::SATURDAY],
                'eligible_pay_types' => [OvertimeRule::PAY_TYPE_HOURLY, OvertimeRule::PAY_TYPE_CONTRACT],
                'requires_prior_day_worked' => true, // Must work Friday for Saturday DT
                'priority' => 10,
                'is_active' => true,
                'description' => '2nd Shift: Saturday is double-time (2.0x) only if Friday was also worked.',
            ]
        );

        // 2nd Shift - 6th consecutive day = 2.0x double-time
        OvertimeRule::updateOrCreate(
            [
                'rule_name' => '2nd Shift 6th Consecutive Day',
                'shift_id' => $secondShift->id,
            ],
            [
                'rule_type' => OvertimeRule::TYPE_CONSECUTIVE_DAY,
                'hours_threshold' => 0,
                'multiplier' => 2.0,
                'double_time_multiplier' => 2.0,
                'consecutive_days_threshold' => 6,
                'eligible_pay_types' => [OvertimeRule::PAY_TYPE_HOURLY, OvertimeRule::PAY_TYPE_CONTRACT],
                'only_applies_to_final_day' => false, // Applies to 6th day and beyond
                'priority' => 5,
                'is_active' => true,
                'description' => '2nd Shift: 6th consecutive day and beyond is double-time (2.0x).',
            ]
        );

        // =====================================================
        // Global Fallback Rules (for employees without shift assignment)
        // =====================================================

        OvertimeRule::updateOrCreate(
            [
                'rule_name' => 'Default Weekly Overtime',
                'shift_id' => null,
            ],
            [
                'rule_type' => OvertimeRule::TYPE_WEEKLY_THRESHOLD,
                'hours_threshold' => 40,
                'multiplier' => 1.5,
                'double_time_multiplier' => 2.0,
                'eligible_pay_types' => [OvertimeRule::PAY_TYPE_HOURLY, OvertimeRule::PAY_TYPE_CONTRACT],
                'priority' => 999, // Low priority fallback
                'is_active' => true,
                'description' => 'Default overtime rule: 1.5x for hours over 40 per week. Applied when no shift-specific rule matches.',
            ]
        );

        $this->command->info('Overtime rules seeded successfully!');
        $this->command->table(
            ['Rule Name', 'Shift', 'Type', 'Threshold', 'Multiplier'],
            OvertimeRule::with('shift')->get()->map(fn ($rule) => [
                $rule->rule_name,
                $rule->shift?->shift_name ?? 'All Shifts',
                $rule->rule_type,
                $rule->hours_threshold.($rule->consecutive_days_threshold ? " / {$rule->consecutive_days_threshold} days" : ''),
                $rule->multiplier.'x',
            ])->toArray()
        );
    }
}
