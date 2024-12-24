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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id()->comment('Primary key of the attendances table');
            $table->unsignedBigInteger('employee_id')->nullable()->comment('Foreign key to employees table');
            $table->string('employee_external_id')->nullable()->comment('External ID of the employee for mapping');
            $table->unsignedBigInteger('device_id')->nullable()->comment('Foreign key to devices table');
            $table->dateTime('punch_time')->nullable()->comment('Time of the punch event');
            $table->boolean('is_manual')->default(false)->comment('Indicates if the attendance was manually recorded');
            $table->unsignedBigInteger('punch_type_id')->nullable()->comment('Foreign key to punch types table');
            $table->text('issue_notes')->nullable()->comment('Notes or issues related to the attendance record');
            $table->enum('status', ['Incomplete', 'Partial', 'Complete', 'Migrated', 'Posted'])
                ->default('Incomplete')
                ->comment('Processing status of the attendance record');
            $table->boolean('is_migrated')->storedAs("`status` = 'Migrated'")->comment('Indicates if the attendance record is marked as migrated');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps();

            // Indexes for optimization
            $table->index('punch_time', 'idx_punch_time')->comment('Index for optimizing queries on punch time');
            $table->index('employee_id', 'idx_employee_id')->comment('Index for optimizing queries by employee');
            $table->index('employee_external_id', 'idx_employee_external_id')->comment('Index for optimizing queries by external employee ID');

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade')->comment('References the employees table');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('set null')->comment('References the devices table');
            $table->foreign('punch_type_id')->references('id')->on('punch_types')->onDelete('set null')->comment('References the punch types table');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for the record creator');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for the last updater');
        });

        // Add triggers for automated validations or calculations
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

            CREATE TRIGGER set_status_on_update
            BEFORE UPDATE ON attendances
            FOR EACH ROW
            BEGIN
                IF NEW.status = 'Migrated' THEN
                    SET NEW.is_migrated = 1;
                END IF;
            END;
        ");
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers first
        DB::unprepared("DROP TRIGGER IF EXISTS validate_punch_time;");
        DB::unprepared("DROP TRIGGER IF EXISTS set_status_on_update;");

        // Drop the table
        Schema::dropIfExists('attendances');
    }
};
