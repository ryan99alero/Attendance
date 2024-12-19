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
        Schema::create('employees', function (Blueprint $table) {
            $table->id()->comment('Primary key of the employees table');
            $table->string('first_name', 50)->comment('First name of the employee');
            $table->string('last_name', 50)->comment('Last name of the employee');
            $table->string('address', 255)->nullable()->comment('Residential address of the employee');
            $table->string('city', 255)->nullable()->comment('City of residence of the employee');
            $table->string('state', 255)->nullable()->comment('State of residence of the employee');
            $table->string('zip', 255)->nullable()->comment('ZIP or postal code of the employee');
            $table->string('country', 255)->nullable()->comment('Country of residence of the employee');
            $table->string('phone', 15)->nullable()->comment('Contact phone number of the employee');
            $table->string('external_id', 255)->nullable()->comment('External system identifier for the employee');
            $table->unsignedBigInteger('department_id')->nullable()->comment('Foreign key referencing departments table');
            $table->unsignedBigInteger('shift_id')->nullable()->comment('Foreign key referencing shifts table');
            $table->enum('rounding_method', ['1_minute', '5_minute', '6_minute', '7_minute', '10_minute', '15_minute'])
                ->nullable()
                ->comment('Rounding method for time calculations');
            $table->string('photograph', 255)->nullable()->comment('Path or URL of the employee photograph');
            $table->date('termination_date')->nullable()->comment('Date of termination, if applicable');
            $table->boolean('is_active')->default(true)->comment('Indicates if the employee is currently active');
            $table->boolean('full_time')->default(false)->comment('Indicates if the employee is a full-time worker');
            $table->boolean('vacation_pay')->default(false)->comment('Indicates if the employee is eligible for vacation pay');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps()->comment('Timestamps for record creation and last update');
            $table->unsignedBigInteger('payroll_frequency_id')->nullable()->comment('Foreign key referencing payroll frequencies table');
            $table->string('full_names', 101)->nullable()->comment('Concatenated full name of the employee');
            $table->unsignedBigInteger('shift_schedule_id')->nullable()->comment('Foreign key referencing shift schedules table');
            $table->unsignedInteger('round_group_id')->nullable()->comment('Foreign key referencing round groups table');

            $table->primary('id')->comment('Defines the primary key of the employees table');

            // Foreign key constraints
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null')->comment('Relationship with departments');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null')->comment('Relationship with shifts');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->comment('User who created the record');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null')->comment('User who last updated the record');
            $table->foreign('payroll_frequency_id')->references('id')->on('payroll_frequencies')->onDelete('set null')->comment('Relationship with payroll frequencies');
            $table->foreign('shift_schedule_id')->references('id')->on('shift_schedules')->onDelete('set null')->comment('Relationship with shift schedules');
            $table->foreign('round_group_id')->references('id')->on('round_groups')->onDelete('set null')->onUpdate('cascade')->comment('Relationship with round groups');
        });

        // Add triggers for full_names
        DB::unprepared("
            CREATE TRIGGER insert_full_name
            BEFORE INSERT ON employees
            FOR EACH ROW
            BEGIN
                SET NEW.full_names = CONCAT(
                    UCASE(LEFT(NEW.first_name, 1)), LCASE(SUBSTRING(NEW.first_name, 2)), ' ',
                    UCASE(LEFT(NEW.last_name, 1)), LCASE(SUBSTRING(NEW.last_name, 2))
                );
            END;

            CREATE TRIGGER update_full_name
            BEFORE UPDATE ON employees
            FOR EACH ROW
            BEGIN
                SET NEW.full_names = CONCAT(
                    UCASE(LEFT(NEW.first_name, 1)), LCASE(SUBSTRING(NEW.first_name, 2)), ' ',
                    UCASE(LEFT(NEW.last_name, 1)), LCASE(SUBSTRING(NEW.last_name, 2))
                );
            END;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers first
        DB::unprepared("DROP TRIGGER IF EXISTS insert_full_name;");
        DB::unprepared("DROP TRIGGER IF EXISTS update_full_name;");

        Schema::dropIfExists('employees');
    }
};
