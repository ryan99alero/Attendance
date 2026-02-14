<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration removes holiday-related columns from vacation_calendars.
     * Holidays are now managed via the holiday_instances table and processed
     * directly to attendance records via HolidayAttendanceService.
     */
    public function up(): void
    {
        // Step 1: Delete all holiday records from vacation_calendars
        // (records where holiday_template_id is not null)
        $deleted = DB::table('vacation_calendars')
            ->whereNotNull('holiday_template_id')
            ->delete();

        DB::statement("SELECT 'Deleted {$deleted} holiday records from vacation_calendars'");

        // Step 2: Remove holiday-related columns
        Schema::table('vacation_calendars', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['holiday_template_id']);

            // Drop the columns
            $table->dropColumn([
                'holiday_template_id',
                'holiday_type',
                'auto_managed',
                'description',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacation_calendars', function (Blueprint $table) {
            $table->foreignId('holiday_template_id')->nullable()->after('is_recorded')
                ->constrained('holiday_templates')->nullOnDelete();
            $table->string('holiday_type', 50)->nullable()->after('holiday_template_id');
            $table->boolean('auto_managed')->default(false)->after('holiday_type');
            $table->string('description')->nullable()->after('auto_managed');
        });
    }
};
