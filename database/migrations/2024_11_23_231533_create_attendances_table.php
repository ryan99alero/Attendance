<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void

    {
        Schema::disableForeignKeyConstraints();
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable()->comment('Foreign key to Employees');
            $table->unsignedBigInteger('device_id')->nullable()->comment('Foreign key to Devices');
            $table->timestamp('check_in')->nullable()->comment('Check-in time');
            $table->timestamp('check_out')->nullable()->comment('Check-out time');
            $table->boolean('is_manual')->default(false)->comment('Indicates if the attendance was manually recorded');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
