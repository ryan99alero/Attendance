<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if the table already exists
        if (Schema::hasTable('schedules')) {
            // If the table exists, modify its structure if needed
            Schema::table('schedules', function (Blueprint $table) {
                // Add or update columns as needed
                if (!Schema::hasColumn('schedules', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->nullable()->comment('Foreign key to Employees');
                    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                }

                if (!Schema::hasColumn('schedules', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable()->comment('Foreign key to Departments');
                    $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
                }

                if (!Schema::hasColumn('schedules', 'shift_id')) {
                    $table->unsignedBigInteger('shift_id')->nullable()->comment('Reference to the shift');
                    $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');
                }

                if (!Schema::hasColumn('schedules', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
                    $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                }

                if (!Schema::hasColumn('schedules', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
                    $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                }

                if (!Schema::hasColumn('schedules', 'schedule_name')) {
                    $table->string('schedule_name')->comment('Name of the schedule');
                }

                if (!Schema::hasColumn('schedules', 'start_time')) {
                    $table->time('start_time')->comment('Scheduled start time');
                }

                if (!Schema::hasColumn('schedules', 'lunch_start_time')) {
                    $table->time('lunch_start_time')->nullable()->comment('Scheduled lunch start time');
                }

                if (!Schema::hasColumn('schedules', 'lunch_duration')) {
                    $table->integer('lunch_duration')->default(60)->comment('Lunch duration in minutes');
                }

                if (!Schema::hasColumn('schedules', 'daily_hours')) {
                    $table->integer('daily_hours')->comment('Standard hours worked per day');
                }

                if (!Schema::hasColumn('schedules', 'end_time')) {
                    $table->time('end_time')->comment('Scheduled end time');
                }

                if (!Schema::hasColumn('schedules', 'grace_period')) {
                    $table->integer('grace_period')->default(15)->comment('Allowed grace period in minutes for lateness');
                }

                if (!Schema::hasColumn('schedules', 'is_active')) {
                    $table->boolean('is_active')->default(true)->comment('Indicates if the schedule is active');
                }

                if (!Schema::hasColumn('schedules', 'notes')) {
                    $table->text('notes')->nullable()->comment('Additional notes for the schedule');
                }
            });
        } else {
            // If the table does not exist, create it
            Schema::create('schedules', function (Blueprint $table) {
                $table->id();
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
                $table->unsignedBigInteger('employee_id')->nullable()->comment('Foreign key to Employees');
                $table->unsignedBigInteger('department_id')->nullable()->comment('Foreign key to Departments');
                $table->unsignedBigInteger('shift_id')->nullable()->comment('Reference to the shift');
                $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
                $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');

                // Foreign key constraints
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
                $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        // Drop the table if it exists
        Schema::dropIfExists('schedules');
    }
};
