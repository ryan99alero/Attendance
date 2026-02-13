<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing indexes for scalability.
 *
 * These indexes address performance issues that will occur at scale
 * (500K+ records in clock_events, attendances, punches tables).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clock Events - processing_error is TEXT type, can't be indexed
        // Queries use whereNull/whereNotNull which don't benefit from indexes on TEXT
        // Note: is_processed column removed - processed events are deleted

        // Attendances - critical for date range and status queries
        Schema::table('attendances', function (Blueprint $table) {
            $table->index('status', 'idx_att_status');
            $table->index('classification_id', 'idx_att_classification_id');
            $table->index('is_posted', 'idx_att_is_posted');
            $table->index(['employee_id', 'punch_time'], 'idx_att_employee_punch_time');
            $table->index(['status', 'punch_time'], 'idx_att_status_punch_time');
        });

        // Punches - critical for pay period and aggregation queries
        Schema::table('punches', function (Blueprint $table) {
            $table->index('pay_period_id', 'idx_punch_pay_period_id');
            $table->index('is_posted', 'idx_punch_is_posted');
            $table->index('classification_id', 'idx_punch_classification_id');
            $table->index(['employee_id', 'punch_time'], 'idx_punch_employee_punch_time');
        });

        // Pay Periods - currently has NO indexes
        Schema::table('pay_periods', function (Blueprint $table) {
            $table->index(['start_date', 'end_date'], 'idx_pp_date_range');
            $table->index('is_processed', 'idx_pp_is_processed');
            $table->index('is_posted', 'idx_pp_is_posted');
            $table->index('processing_status', 'idx_pp_processing_status');
        });

        // Pay Period Employee Summaries - for finalization queries
        Schema::table('pay_period_employee_summaries', function (Blueprint $table) {
            $table->index('is_finalized', 'idx_ppes_is_finalized');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_att_status');
            $table->dropIndex('idx_att_classification_id');
            $table->dropIndex('idx_att_is_posted');
            $table->dropIndex('idx_att_employee_punch_time');
            $table->dropIndex('idx_att_status_punch_time');
        });

        Schema::table('punches', function (Blueprint $table) {
            $table->dropIndex('idx_punch_pay_period_id');
            $table->dropIndex('idx_punch_is_posted');
            $table->dropIndex('idx_punch_classification_id');
            $table->dropIndex('idx_punch_employee_punch_time');
        });

        Schema::table('pay_periods', function (Blueprint $table) {
            $table->dropIndex('idx_pp_date_range');
            $table->dropIndex('idx_pp_is_processed');
            $table->dropIndex('idx_pp_is_posted');
            $table->dropIndex('idx_pp_processing_status');
        });

        Schema::table('pay_period_employee_summaries', function (Blueprint $table) {
            $table->dropIndex('idx_ppes_is_finalized');
        });
    }
};
