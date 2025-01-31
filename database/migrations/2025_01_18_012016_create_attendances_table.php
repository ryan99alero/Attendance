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
            $table->unsignedBigInteger('employee_id')->comment('Foreign key to employees table');
            $table->unsignedBigInteger('device_id')->nullable()->comment('Foreign key to devices table');
            $table->dateTime('punch_time')->nullable()->comment('Time of the punch event');
            $table->boolean('is_manual')->default(false)->comment('Indicates if the attendance was manually recorded');
            $table->unsignedBigInteger('punch_type_id')->nullable()->comment('Foreign key to punch types table');
            $table->text('issue_notes')->nullable()->comment('Notes or issues related to the attendance record');
            $table->enum('status', ['Incomplete', 'Partial', 'Complete', 'Migrated', 'Posted'])
                ->default('Incomplete')->comment('Processing status of the attendance record');
            $table->boolean('is_migrated')->storedAs("`status` = 'Migrated'")->comment('Indicates if the attendance record is migrated');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps();
            $table->string('employee_external_id', 255)->nullable()->comment('External ID of the employee for mapping');
            $table->unsignedBigInteger('classification_id')->nullable()->comment('Foreign key to classification table');

            // Indexes
            $table->index('punch_time', 'idx_punch_time');
            $table->index('employee_id', 'idx_employee_id');
            $table->index('employee_external_id', 'idx_employee_external_id');

            // Foreign keys
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('set null');
            $table->foreign('punch_type_id')->references('id')->on('punch_types')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('classification_id')->references('id')->on('classifications')->onDelete('set null');
            $table->foreign('holiday_id')->references('id')->on('holidays')->onDelete('cascade');
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
                IF NEW.employee_id IS NULL AND NEW.employee_external_id IS NOT NULL THEN
                    SET NEW.employee_id = (
                        SELECT id
                        FROM employees
                        WHERE external_id = NEW.employee_external_id
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
