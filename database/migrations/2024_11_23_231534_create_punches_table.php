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
        Schema::disableForeignKeyConstraints();

        Schema::create('punches', function (Blueprint $table) {
            $table->id()->comment('Primary key of the punches table');

            // Employee and device references
            $table->unsignedBigInteger('employee_id')->nullable()->comment('Foreign key referencing the employee who made the punch');
            $table->string('external_group_id', 40)->nullable()->comment('Links to attendance_time_groups.external_group_id');
            $table->date('shift_date')->nullable()->comment('The assigned workday for this punch record');
            $table->unsignedBigInteger('device_id')->nullable()->comment('Foreign key referencing the device used for the punch');

            // Punch details
            $table->unsignedBigInteger('punch_type_id')->nullable()->comment('Foreign key referencing the type of punch (e.g., Clock In, Clock Out)');
            $table->dateTime('punch_time')->comment('Exact time of the punch');
            $table->boolean('is_altered')->default(false)->comment('Indicates if the punch was manually altered after recording');
            $table->boolean('is_late')->default(false)->comment('Indicates if the punch is considered late');
            $table->enum('punch_state', ['start', 'stop', 'unknown'])
                ->after('punch_type_id')
                ->default('unknown')
                ->comment('Indicates whether the punch is a start or stop event');

            // Processing and classification
            $table->unsignedBigInteger('pay_period_id')->nullable()->comment('Foreign key referencing the associated pay period');
            $table->unsignedBigInteger('attendance_id')->nullable()->comment('Foreign key referencing the associated attendance record');
            $table->unsignedBigInteger('classification_id')->nullable()->comment('Foreign key referencing the classifications table');
            $table->boolean('is_processed')->default(false)->comment('Indicates if the punch has been processed in the system');
            $table->boolean('is_archived')->default(false)->comment('Indicates if the record is archived');

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps();

            // Indexes for optimization
            $table->index('employee_id', 'idx_employee_id');
            $table->index('punch_time', 'idx_punch_time');
            $table->index('external_group_id', 'idx_external_group_id');
            $table->index('shift_date', 'idx_shift_date');
            $table->index('is_archived', 'idx_is_archived');

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('set null');
            $table->foreign('punch_type_id')->references('id')->on('punch_types')->onDelete('set null');
            $table->foreign('pay_period_id')->references('id')->on('pay_periods')->onDelete('set null');
            $table->foreign('attendance_id')->references('id')->on('attendances')->onDelete('set null');
            $table->foreign('classification_id')->references('id')->on('classifications')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });

        // Create the trigger
        DB::unprepared('
            CREATE TRIGGER before_punch_time_update
            BEFORE UPDATE ON punches
            FOR EACH ROW
            BEGIN
                IF NEW.punch_time != OLD.punch_time THEN
                    SET NEW.is_altered = 1;
                END IF;
            END
        ');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the trigger before dropping the table
        DB::unprepared('DROP TRIGGER IF EXISTS before_punch_time_update');

        Schema::dropIfExists('punches');
    }
};
