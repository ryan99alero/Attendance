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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('scheduleName', 255); // Name of the schedule (e.g., "Day Shift")
            $table->time('startTime'); // Shift start time
            $table->time('endTime'); // Shift end time
            $table->float('hoursPerDay', 5, 2)->default(8); // Standard hours per day
            $table->boolean('isActive')->default(true); // Soft deletion flag
            $table->unsignedBigInteger('createdBy')->nullable(); // Admin/system who created
            $table->unsignedBigInteger('updatedBy')->nullable(); // Admin/system who updated
            $table->timestamps(); // Created and updated timestamps

            // Foreign key constraints
            $table->foreign('createdBy')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updatedBy')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
