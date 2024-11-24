<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('vacation_calendars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->comment('Foreign key to Employees');
            $table->date('vacation_date')->comment('Date of the vacation');
            $table->boolean('is_half_day')->default(false)->comment('Indicates if the vacation is a half-day');
            $table->boolean('is_active')->default(true)->comment('Indicates if the vacation record is active');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_calendars');
    }
};
