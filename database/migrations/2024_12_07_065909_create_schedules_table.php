<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable()->comment('Foreign key to Employees');
            $table->unsignedBigInteger('department_id')->nullable()->comment('Foreign key to Departments');
            $table->string('schedule_name')->comment('Name of the schedule');
            $table->time('start_time')->comment('Scheduled start time');
            $table->time('lunch_start_time')->nullable()->comment('Scheduled lunch start time');
            $table->integer('lunch_duration')->default(60)->comment('Lunch duration in minutes');
            $table->integer('daily_hours')->comment('Standard hours worked per day');
            $table->time('end_time')->comment('Scheduled end time');
            $table->integer('grace_period')->default(15)->comment('Allowed grace period in minutes for lateness');
            $table->unsignedBigInteger('shift_id')->nullable()->comment('Reference to the shift');
            $table->boolean('is_active')->default(true)->comment('Indicates if the schedule is active');
            $table->text('notes')->nullable()->comment('Additional notes for the schedule');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps(); // Automatically adds created_at and updated_at columns

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
