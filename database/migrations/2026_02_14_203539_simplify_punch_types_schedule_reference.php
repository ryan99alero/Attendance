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
     * Simplifies punch_types.schedule_reference from multiple enum values
     * (start_time, stop_time, lunch_start, lunch_stop, flexible, passthrough, etc.)
     * to a simpler punch_direction column with just 'start', 'stop', or null.
     */
    public function up(): void
    {
        // Step 1: Add the new punch_direction column
        Schema::table('punch_types', function (Blueprint $table) {
            $table->enum('punch_direction', ['start', 'stop'])
                ->nullable()
                ->after('schedule_reference')
                ->comment('Direction of the punch: start (clocking in/resuming), stop (clocking out/pausing), or null');
        });

        // Step 2: Migrate data from schedule_reference to punch_direction
        // start_time, lunch_stop → 'start' (resuming work)
        // stop_time, lunch_start → 'stop' (stopping work)
        // null, flexible, passthrough → null
        DB::statement("
            UPDATE punch_types
            SET punch_direction = CASE
                WHEN schedule_reference IN ('start_time', 'lunch_stop') THEN 'start'
                WHEN schedule_reference IN ('stop_time', 'lunch_start') THEN 'stop'
                ELSE NULL
            END
        ");

        // Step 3: Drop the old schedule_reference column
        Schema::table('punch_types', function (Blueprint $table) {
            $table->dropColumn('schedule_reference');
        });

        // Step 4: Delete unused punch types (Flexible Time and Pass Through)
        DB::table('punch_types')
            ->whereIn('name', ['Flexible Time', 'Pass Through'])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Re-add schedule_reference column with original enum values
        Schema::table('punch_types', function (Blueprint $table) {
            $table->enum('schedule_reference', [
                'start_time',
                'lunch_start',
                'lunch_stop',
                'stop_time',
                'break_start',
                'break_stop',
                'manual',
                'jury_duty',
                'bereavement',
                'flexible',
                'passthrough',
            ])->nullable()->after('description');
        });

        // Step 2: Migrate data back (best effort - some data may be lost)
        DB::statement("
            UPDATE punch_types
            SET schedule_reference = CASE
                WHEN name = 'Clock In' THEN 'start_time'
                WHEN name = 'Clock Out' THEN 'stop_time'
                WHEN name = 'Lunch Start' THEN 'lunch_start'
                WHEN name = 'Lunch Stop' THEN 'lunch_stop'
                WHEN name = 'Break Start' THEN 'start_time'
                WHEN name = 'Break End' THEN 'stop_time'
                ELSE NULL
            END
        ");

        // Step 3: Re-add deleted punch types
        DB::table('punch_types')->insert([
            ['name' => 'Flexible Time', 'schedule_reference' => 'flexible', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Pass Through', 'schedule_reference' => 'passthrough', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Step 4: Drop punch_direction column
        Schema::table('punch_types', function (Blueprint $table) {
            $table->dropColumn('punch_direction');
        });
    }
};
