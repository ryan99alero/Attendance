<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 50)->comment('First name of the employee');
            $table->string('last_name', 50)->comment('Last name of the employee');
            $table->string('address', 255)->nullable()->comment('Employee address');
            $table->string('city', 255)->nullable()->comment('City of the employee');
            $table->string('state', 255)->nullable()->comment('State of the employee');
            $table->string('zip', 255)->nullable()->comment('ZIP code of the employee');
            $table->string('country', 255)->nullable()->comment('Country of the employee');
            $table->string('phone', 15)->nullable()->comment('Phone number of the employee');
            $table->string('external_id', 255)->nullable()->comment('External ID of the employee');
            $table->unsignedBigInteger('department_id')->nullable()->comment('Foreign key to Departments');
            $table->unsignedBigInteger('shift_id')->nullable()->comment('Foreign key to Shifts');
            $table->unsignedBigInteger('rounding_method')->nullable()->comment('Foreign key to Rounding Rules');
            $table->unsignedBigInteger('payroll_frequency_id')->nullable()->comment('Foreign key to Payroll Frequencies');
            $table->float('normal_hrs_per_day')->nullable()->comment('Normal hours per day for the employee');
            $table->boolean('paid_lunch')->default(false)->comment('Indicates if the employee has a paid lunch');
            $table->string('photograph', 255)->nullable()->comment('Photograph path or URL');
            $table->date('start_date')->nullable()->comment('Start date of the employee');
            $table->time('start_time')->nullable()->comment('Start time of the employee');
            $table->time('stop_time')->nullable()->comment('Stop time of the employee');
            $table->date('termination_date')->nullable()->comment('Termination date of the employee');
            $table->boolean('is_active')->default(true)->comment('Indicates if the employee is active');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');
            $table->foreign('rounding_method')->references('id')->on('rounding_rules')->onDelete('set null');
            $table->foreign('payroll_frequency_id')->references('id')->on('payroll_frequencies')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employees');
    }
};
