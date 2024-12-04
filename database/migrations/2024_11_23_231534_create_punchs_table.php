<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('punches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable()->comment('Foreign key to Employees');
            $table->unsignedBigInteger('device_id')->nullable()->comment('Foreign key to Devices');
            $table->unsignedBigInteger('punch_type_id')->nullable()->comment('Foreign key to Punch Types');
            $table->unsignedBigInteger('pay_period_id')->nullable()->comment('Foreign key to Pay Periods'); // New field
            $table->timestamp('time_in')->comment('Actual punch-in time');
            $table->timestamp('time_out')->nullable()->comment('Actual punch-out time');
            $table->boolean('is_altered')->default(false)->comment('Indicates if the punch was altered post-recording');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('set null');
            $table->foreign('punch_type_id')->references('id')->on('punch_types')->onDelete('set null');
            $table->foreign('pay_period_id')->references('id')->on('pay_periods')->onDelete('set null'); // New constraint
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punches');
    }
};
