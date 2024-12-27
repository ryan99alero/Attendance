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
            $table->string('email')->primary()->comment('Employee email address of the employee');
            $table->unsignedBigInteger('department_id')->nullable()->comment('Foreign key referencing the departments table');
            $table->unsignedBigInteger('shift_id')->nullable()->comment('Foreign key referencing the shifts table');
            $table->string('photograph', 255)->nullable()->comment('Path or URL of the employee photograph');
            $table->date('termination_date')->nullable()->comment('Date of termination, if applicable');
            $table->boolean('is_active')->default(true)->comment('Indicates if the employee is currently active');
            $table->boolean('full_time')->default(false)->comment('Indicates if the employee is a full-time worker');
            $table->boolean('vacation_pay')->default(false)->comment('Indicates if the employee is eligible for vacation pay');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps();
            $table->unsignedBigInteger('payroll_frequency_id')->nullable()->comment('Foreign key referencing the payroll frequencies table');
            $table->string('full_names', 101)->nullable()->comment('Concatenated full name of the employee');
            $table->unsignedBigInteger('shift_schedule_id')->nullable()->comment('Foreign key referencing the shift schedules table');
            $table->unsignedBigInteger('round_group_id')->nullable()->comment('Foreign key referencing the round_groups table');

            // Foreign key constraints
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null')->comment('Relationship with departments');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null')->comment('Relationship with shifts');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->comment('User who created the record');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null')->comment('User who last updated the record');
            $table->foreign('payroll_frequency_id')->references('id')->on('payroll_frequencies')->onDelete('set null')->comment('Relationship with payroll frequencies');
            $table->foreign('shift_schedule_id')->references('id')->on('shift_schedules')->onDelete('set null')->comment('Relationship with shift schedules');
            $table->foreign('round_group_id')->references('id')->on('round_groups')->onDelete('set null')->onUpdate('cascade')->comment('Relationship with round groups');

            // Indexes for optimization
            $table->index(['first_name', 'last_name'], 'idx_employee_name')->comment('Index for optimizing queries by employee name');
            $table->index('department_id', 'idx_department_id')->comment('Index for optimizing queries by department');
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
        Schema::enableForeignKeyConstraints();
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
