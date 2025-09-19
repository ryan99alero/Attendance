<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing holidays to holiday templates
        $oldHolidays = DB::table('holidays')->get();

        foreach ($oldHolidays as $holiday) {
            $startDate = Carbon::parse($holiday->start_date);

            // Create holiday template from old holiday
            DB::table('holiday_templates')->insert([
                'name' => $holiday->name,
                'type' => 'fixed_date',
                'calculation_rule' => json_encode([
                    'month' => $startDate->month,
                    'day' => $startDate->day,
                ]),
                'auto_create_days_ahead' => 365,
                'applies_to_all_employees' => true,
                'eligible_pay_types' => json_encode(['salary', 'hourly_fulltime']),
                'is_active' => true,
                'description' => "Migrated from old holidays table",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Log the migration
        if ($oldHolidays->count() > 0) {
            echo "✅ Migrated {$oldHolidays->count()} holidays to holiday templates\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove migrated holiday templates
        DB::table('holiday_templates')
            ->where('description', 'Migrated from old holidays table')
            ->delete();
    }
};