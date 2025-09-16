<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('company_setup', function (Blueprint $table) {
            $table->id();
            $table->integer('attendance_flexibility_minutes')->default(30)
                ->comment('Number of minutes allowed before/after a shift for attendance matching');
            $table->enum('logging_level', ['none', 'error', 'warning', 'info', 'debug'])
                ->default('error')
                ->comment('Defines the level of logging in the system');
            $table->boolean('auto_adjust_punches')->default(false)
                ->comment('Whether to automatically adjust punch types for incomplete records');
            $table->integer('heuristic_min_punch_gap')->default(6)
                ->comment('Minimum hours required between punches for auto-classification');
            $table->boolean('use_ml_for_punch_matching')->default(true)
                ->comment('Enable ML-based punch classification');
            $table->boolean('enforce_shift_schedules')->default(true)
                ->comment('Require employees to adhere to assigned shift schedules');
            $table->boolean('allow_manual_time_edits')->default(true)
                ->comment('Allow admins to manually edit time records');
            $table->integer('max_shift_length')->default(12)
                ->comment('Maximum shift length in hours before requiring admin approval');

            // NEW FIELD: Debug mode for punch assignment
            $table->enum('debug_punch_assignment_mode', ['heuristic', 'ml', 'consensus', 'all'])
                ->default('all')
                ->comment('Controls which Punch Type Assignment service runs for debugging');

            // Payroll Frequency (moved from employee level to company level)
            $table->unsignedBigInteger('payroll_frequency_id')
                ->nullable()
                ->comment('Company-wide payroll frequency - all employees follow the same schedule');

            // Payroll Start Date (when the company first implemented this payroll schedule)
            $table->date('payroll_start_date')
                ->nullable()
                ->comment('Date when the company started using the current payroll frequency (used for bi-weekly cycle calculations)');

            $table->timestamps();

            // Foreign key constraint for payroll frequency
            $table->foreign('payroll_frequency_id')
                ->references('id')->on('payroll_frequencies')
                ->onDelete('set null');
        });
    }

    public function down() {
        Schema::dropIfExists('company_setup');
    }
};
