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

        Schema::create('vacation_calendars', function (Blueprint $table) {
            $table->id()->comment('Primary key of the vacation_calendars table');
            $table->unsignedBigInteger('employee_id')->comment('Foreign key referencing the employee taking the vacation');
            $table->date('vacation_date')->comment('Date of the vacation');
            $table->boolean('is_half_day')->default(false)->comment('Indicates if the vacation is for a half-day');
            $table->boolean('is_active')->default(true)->comment('Indicates if the vacation record is active');
            $table->boolean('is_recorded')->default(false)->comment('Indicates if this vacation has been recorded in the Attendance table');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps()->comment('Timestamps for record creation and updates');

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade')->comment('References the employees table');
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
        Schema::dropIfExists('vacation_calendars');
    }
};
