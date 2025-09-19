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
            // Clock Event Processing Settings
            $table->enum('clock_event_sync_frequency', [
                'real_time',       // Process immediately (push)
                'every_minute',    // Every 1 minute
                'every_5_minutes', // Every 5 minutes
                'every_15_minutes',// Every 15 minutes
                'every_30_minutes',// Every 30 minutes
                'hourly',          // Every hour
                'twice_daily',     // Twice per day (8am, 6pm)
                'daily',           // Once per day (6am)
                'manual_only'      // Only when manually triggered
            ])->default('every_5_minutes')->after('max_shift_length');

            $table->integer('clock_event_batch_size')
                ->default(100)
                ->comment('Number of events to process per batch')
                ->after('clock_event_sync_frequency');

            $table->boolean('clock_event_auto_retry_failed')
                ->default(true)
                ->comment('Automatically retry failed clock event processing')
                ->after('clock_event_batch_size');

            $table->time('clock_event_daily_sync_time')
                ->default('06:00:00')
                ->comment('Time of day for daily sync (when using daily frequency)')
                ->after('clock_event_auto_retry_failed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_setup', function (Blueprint $table) {
            $table->dropColumn([
                'clock_event_sync_frequency',
                'clock_event_batch_size',
                'clock_event_auto_retry_failed',
                'clock_event_daily_sync_time'
            ]);
        });
    }
};
