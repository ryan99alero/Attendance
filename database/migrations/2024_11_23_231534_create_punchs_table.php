<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->unsignedBigInteger('employee_id')->nullable()->comment('Foreign key referencing the employee who made the punch');
            $table->unsignedBigInteger('device_id')->nullable()->comment('Foreign key referencing the device used for the punch');
            $table->unsignedBigInteger('punch_type_id')->nullable()->comment('Foreign key referencing the type of punch (e.g., Clock In, Clock Out)');
            $table->unsignedBigInteger('pay_period_id')->nullable()->comment('Foreign key referencing the associated pay period');
            $table->unsignedBigInteger('attendance_id')->nullable()->comment('Foreign key referencing the associated attendance record');
            $table->dateTime('punch_time')->comment('Exact time of the punch');
            $table->boolean('is_altered')->default(false)->comment('Indicates if the punch was manually altered after recording');
            $table->boolean('is_late')->default(false)->comment('Indicates if the punch is considered late');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps();

            // Indexes for optimization
            $table->index('employee_id', 'idx_employee_id')->comment('Index for optimizing queries by employee ID');
            $table->index('punch_time', 'idx_punch_time')->comment('Index for optimizing queries by punch time');

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade')->comment('References the employees table');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('set null')->comment('References the devices table');
            $table->foreign('punch_type_id')->references('id')->on('punch_types')->onDelete('set null')->comment('References the punch_types table');
            $table->foreign('pay_period_id')->references('id')->on('pay_periods')->onDelete('set null')->comment('References the pay_periods table');
            $table->foreign('attendance_id')->references('id')->on('attendances')->onDelete('set null')->comment('References the attendances table');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for the record creator');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for the last updater');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('punches');
    }
};
