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
        Schema::table('company_setup', function (Blueprint $table) {
            // Calendar Year Method Fields
            $table->date('calendar_year_award_date')->nullable()->comment('Date to award annual vacation (e.g., January 1st)');
            $table->boolean('calendar_year_prorate_partial')->default(true)->comment('Prorate vacation for partial year employment');

            // Pay Period Method Fields
            $table->decimal('pay_period_hours_per_period', 8, 4)->nullable()->comment('Hours accrued per pay period');
            $table->boolean('pay_period_accrue_immediately')->default(true)->comment('Start accruing from first pay period');
            $table->integer('pay_period_waiting_periods')->default(0)->comment('Number of pay periods to wait before accruing');

            // Anniversary Method Fields
            $table->boolean('anniversary_first_year_waiting_period')->default(true)->comment('Wait until first anniversary to award vacation');
            $table->boolean('anniversary_award_on_anniversary')->default(true)->comment('Award full year vacation on anniversary date');
            $table->integer('anniversary_max_days_cap')->nullable()->comment('Maximum vacation days cap (leave null for policy-based cap)');
            $table->boolean('anniversary_allow_partial_year')->default(false)->comment('Allow partial year accrual in first year');

            // Remove the old JSON field since we're using real fields now
            $table->dropColumn('vacation_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_setup', function (Blueprint $table) {
            // Restore JSON field
            $table->json('vacation_config')->nullable()->comment('Method-specific configuration JSON');

            // Remove method-specific fields
            $table->dropColumn([
                'calendar_year_award_date',
                'calendar_year_prorate_partial',
                'pay_period_hours_per_period',
                'pay_period_accrue_immediately',
                'pay_period_waiting_periods',
                'anniversary_first_year_waiting_period',
                'anniversary_award_on_anniversary',
                'anniversary_max_days_cap',
                'anniversary_allow_partial_year',
            ]);
        });
    }
};
