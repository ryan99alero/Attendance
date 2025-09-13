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
        // Create the schedules table
        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->id()->comment('Primary key of the schedules table');
            $table->string('schedule_name')->comment('Name of the schedule');
            $table->time('start_time')->comment('Scheduled start time');
            $table->time('lunch_start_time')->nullable()->comment('Scheduled lunch start time');
            $table->integer('lunch_duration')->default(60)->comment('Lunch duration in minutes');
            $table->integer('daily_hours')->comment('Standard hours worked per day');
            $table->time('end_time')->comment('Scheduled end time');
            $table->integer('grace_period')->default(15)->comment('Allowed grace period in minutes for lateness');
            $table->boolean('is_active')->default(true)->comment('Indicates if the schedule is active');
            $table->text('notes')->nullable()->comment('Additional notes for the schedule');
            $table->timestamps();

            // Foreign key columns
            $table->unsignedBigInteger('employee_id')->nullable()->comment('Foreign key referencing the employees table');
            $table->unsignedBigInteger('shift_id')->nullable()->comment('Foreign key referencing the shifts table');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade')->comment('References the employees table');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null')->comment('References the shifts table');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for the record creator');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for the last updater');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the schedules table
        Schema::dropIfExists('shift_schedules');
    }
};
