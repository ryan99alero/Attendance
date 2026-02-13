<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove unused columns from clock_events table.
 *
 * These columns are no longer needed because successfully processed
 * clock events are now deleted from the table (they live in attendances).
 * Only pending/errored events remain in clock_events.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            // Drop composite index that includes is_processed
            $table->dropIndex('clock_events_employee_id_shift_date_is_processed_index');
        });

        Schema::table('clock_events', function (Blueprint $table) {
            // Drop foreign key constraint before dropping the column
            $table->dropForeign(['attendance_id']);
        });

        Schema::table('clock_events', function (Blueprint $table) {
            $table->dropColumn([
                'is_processed',
                'processed_at',
                'attendance_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            $table->boolean('is_processed')->default(false)->after('notes');
            $table->timestamp('processed_at')->nullable()->after('is_processed');
            $table->unsignedBigInteger('attendance_id')->nullable()->after('processed_at');

            // Recreate the composite index
            $table->index(['employee_id', 'shift_date', 'is_processed'], 'clock_events_employee_id_shift_date_is_processed_index');
            $table->foreign('attendance_id')->references('id')->on('attendances')->nullOnDelete();
        });
    }
};
