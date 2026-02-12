<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('overtime_rules', function (Blueprint $table) {
            // Rule type for different overtime calculation scenarios
            $table->enum('rule_type', [
                'weekly_threshold',
                'daily_threshold',
                'weekend_day',
                'consecutive_day',
                'holiday',
            ])->default('weekly_threshold')->after('rule_name')
                ->comment('Type of overtime rule: weekly/daily threshold, weekend day, consecutive day, or holiday');

            // Day-of-week targeting (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
            $table->json('applies_to_days')->nullable()->after('applies_on_weekends')
                ->comment('JSON array of day numbers [0-6] this rule applies to');

            // Pay type filtering
            $table->json('eligible_pay_types')->nullable()->after('applies_to_days')
                ->comment('JSON array of pay types eligible for this rule: hourly, salary, contract');

            // Prior day verification for double-time rules
            $table->boolean('requires_prior_day_worked')->default(false)->after('eligible_pay_types')
                ->comment('Whether the prior day must be worked to trigger this rule (e.g., Sunday DT requires Saturday worked)');

            // For consecutive day rules - only applies on the final qualifying day
            $table->boolean('only_applies_to_final_day')->default(false)->after('requires_prior_day_worked')
                ->comment('For consecutive day rules, only apply on the threshold day itself');

            // Double-time multiplier (separate from regular overtime multiplier)
            $table->decimal('double_time_multiplier', 5, 2)->default(2.00)->after('only_applies_to_final_day')
                ->comment('Multiplier for double-time pay');

            // Priority for rule ordering (lower = higher priority)
            $table->integer('priority')->default(100)->after('double_time_multiplier')
                ->comment('Priority for rule ordering when multiple rules match (lower = higher priority)');

            // Active status
            $table->boolean('is_active')->default(true)->after('priority')
                ->comment('Whether this overtime rule is currently active');

            // Description for administrators
            $table->text('description')->nullable()->after('is_active')
                ->comment('Detailed description of what this overtime rule does');

            // Index for common queries
            $table->index(['rule_type', 'is_active', 'priority'], 'idx_rule_type_active_priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overtime_rules', function (Blueprint $table) {
            $table->dropIndex('idx_rule_type_active_priority');

            $table->dropColumn([
                'rule_type',
                'applies_to_days',
                'eligible_pay_types',
                'requires_prior_day_worked',
                'only_applies_to_final_day',
                'double_time_multiplier',
                'priority',
                'is_active',
                'description',
            ]);
        });
    }
};
