<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('employee_stats', function (Blueprint $table) {
            $table->id()->comment('Primary key of the employee_stats table');
            $table->unsignedBigInteger('employee_id')->comment('Foreign key to Employees');
            $table->integer('hours_worked')->default(0)->comment('Total hours worked');
            $table->integer('overtime_hours')->default(0)->comment('Total overtime hours');
            $table->integer('leave_days')->default(0)->comment('Total leave days');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade')->comment('References the employees table');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for record creator');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for last updater');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_stats');
    }
};
