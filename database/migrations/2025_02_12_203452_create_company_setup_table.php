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
            $table->boolean('use_ml_for_punch_matching')->default(true)
                ->comment('Enable ML-based punch classification');
            $table->boolean('enforce_shift_schedules')->default(true)
                ->comment('Require employees to adhere to assigned shift schedules');
            $table->boolean('allow_manual_time_edits')->default(true)
                ->comment('Allow admins to manually edit time records');
            $table->integer('max_shift_length')->default(12)
                ->comment('Maximum shift length in hours before requiring admin approval');

            // NEW FIELD: Debug mode for punch assignment
            $table->enum('debug_punch_assignment_mode', ['shift_schedule', 'heuristic', 'ml', 'full'])
                ->default('full')
                ->comment('Controls which Punch Type Assignment service runs for debugging');

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('company_setup');
    }
};
