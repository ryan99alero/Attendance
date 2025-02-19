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
        Schema::create('attendance_time_groups', function (Blueprint $table) {
            $table->id()->comment('Primary key of the attendance_time_groups table');

            // Core fields
            $table->unsignedBigInteger('employee_id')->comment('Foreign key to employees table');
            $table->string('external_group_id', 40)->unique()->comment('Unique ID for this time group, format: employee_external_id + shift_date');
            $table->date('shift_date')->nullable()->comment('The official workday this shift is assigned to');
            $table->dateTime('shift_window_start')->nullable()->comment('Start of the work period for this shift group');
            $table->dateTime('shift_window_end')->nullable()->comment('End of the work period for this shift group');

            // Archival flag
            $table->boolean('is_archived')->default(false)->comment('Indicates if the record is archived');

            // Timestamps
            $table->timestamps();

            // Indexes for optimization
            $table->index(['employee_id', 'shift_date'], 'idx_employee_shift_date');
            $table->index('external_group_id', 'idx_external_group_id');
            $table->index('is_archived', 'idx_is_archived');

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_time_groups');
    }
};
