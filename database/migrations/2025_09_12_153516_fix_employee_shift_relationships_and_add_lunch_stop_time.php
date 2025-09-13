<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key checks for the operation
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::disableForeignKeyConstraints();

        // 1. lunch_stop_time already exists, skipping

        // 2. Remove employee_id from shift_schedules (many employees can share one schedule)
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
        });

        // 3. Fix employee shift_id - remove foreign key constraint but keep column as cache
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            // Column already exists, just removing the constraint
            // shift_id becomes a denormalized cache field
        });

        // 4. Sync existing shift_id values from shift_schedules
        DB::statement('
            UPDATE employees e
            JOIN shift_schedules ss ON e.shift_schedule_id = ss.id
            SET e.shift_id = ss.shift_id
            WHERE e.shift_schedule_id IS NOT NULL
        ');

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::disableForeignKeyConstraints();

        // Reverse the changes
        
        // 1. Add back employee_id to shift_schedules
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->comment('Foreign key referencing the employees table');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });

        // 2. Add back foreign key constraint to employees.shift_id
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');
        });

        // 3. lunch_stop_time already existed, no need to remove

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        Schema::enableForeignKeyConstraints();
    }
};