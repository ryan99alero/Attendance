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
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::create('attendances', function (Blueprint $table) {
            $table->id()->comment('Primary key of the attendances table');

            // Employee and related identifiers
            $table->unsignedBigInteger('employee_id')->comment('Foreign key to employees table');
            $table->string('employee_external_id', 255)->nullable()->comment('External ID of the employee for mapping');

            // Punch details
            $table->dateTime('punch_time')->nullable()->comment('Time of the punch event');
            $table->unsignedBigInteger('device_id')->nullable()->comment('Foreign key to devices table');
            $table->unsignedBigInteger('punch_type_id')->nullable()->comment('Foreign key to punch types table');
            $table->boolean('is_manual')->default(false)->comment('Indicates if the attendance was manually recorded');
            $table->enum('punch_state', ['start', 'stop', 'unknown'])
                ->after('punch_type_id')
                ->default('unknown')
                ->comment('Indicates whether the punch is a start or stop event');

            // Shift-related fields
            $table->string('external_group_id', 40)->nullable()->comment('Links to attendance_time_groups.external_group_id');
            $table->date('shift_date')->nullable()->comment('The assigned workday for this attendance record');

            // Processing status and categorization
            $table->enum('status', ['Incomplete','Partial','Complete','Discrepancy','Migrated','Posted','NeedsReview'])
                ->default('Incomplete')->comment('Processing status of the attendance record');
            $table->boolean('is_migrated')->storedAs("`status` = 'Migrated'")->comment('Indicates if the attendance record is migrated');
            $table->boolean('is_posted')->default(false)->comment('Indicates if the pay period has been processed');
            $table->unsignedBigInteger('classification_id')->nullable()->comment('Foreign key to classification table');
            $table->unsignedBigInteger('holiday_id')->nullable()->comment('Foreign key to holidays table');

            // Archival status
            $table->boolean('is_archived')->default(false)->comment('Indicates if the record is archived');

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps();

            // Indexes
            $table->index('punch_time', 'idx_punch_time');
            $table->index('employee_id', 'idx_employee_id');
            $table->index('employee_external_id', 'idx_employee_external_id');
            $table->index('external_group_id', 'idx_external_group_id');
            $table->index('shift_date', 'idx_shift_date');
            $table->index('is_archived', 'idx_is_archived');

            // Foreign keys
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('set null');
            $table->foreign('punch_type_id')->references('id')->on('punch_types')->onDelete('set null');
            $table->foreign('classification_id')->references('id')->on('classifications')->onDelete('set null');
            $table->foreign('holiday_id')->references('id')->on('holidays')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Triggers
        DB::unprepared("
            CREATE TRIGGER validate_punch_time
            BEFORE INSERT ON attendances
            FOR EACH ROW
            BEGIN
                IF NEW.punch_time IS NULL THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Punch time cannot be NULL.';
                END IF;
            END;

            CREATE TRIGGER set_employee_id_from_external_id
            BEFORE INSERT ON attendances
            FOR EACH ROW
            BEGIN
                -- If employee_id is missing, find it using employee_external_id
                IF NEW.employee_id IS NULL AND NEW.employee_external_id IS NOT NULL THEN
                    SET NEW.employee_id = (
                        SELECT id
                        FROM employees
                        WHERE external_id = NEW.employee_external_id
                        LIMIT 1
                    );
                END IF;

                -- If employee_external_id is missing, find it using employee_id
                IF NEW.employee_external_id IS NULL AND NEW.employee_id IS NOT NULL THEN
                    SET NEW.employee_external_id = (
                        SELECT external_id
                        FROM employees
                        WHERE id = NEW.employee_id
                        LIMIT 1
                    );
                END IF;
            END;

            CREATE TRIGGER set_status_default
            BEFORE INSERT ON attendances
            FOR EACH ROW
            BEGIN
                IF NEW.status IS NULL OR NEW.status = '' THEN
                    SET NEW.status = 'Incomplete';
                END IF;
            END;

            CREATE TRIGGER set_status_on_update
            BEFORE UPDATE ON attendances
            FOR EACH ROW
            BEGIN
                IF NEW.status = 'Migrated' THEN
                    SET NEW.is_migrated = 1;
                END IF;
            END;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS validate_punch_time;");
        DB::unprepared("DROP TRIGGER IF EXISTS set_employee_id_from_external_id;");
        DB::unprepared("DROP TRIGGER IF EXISTS set_status_default;");
        DB::unprepared("DROP TRIGGER IF EXISTS set_status_on_update;");
        Schema::dropIfExists('attendances');
    }
};
