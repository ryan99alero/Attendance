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
        Schema::create('attendance', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('employeeId'); // Foreign key to employees table
            $table->unsignedBigInteger('deviceId')->nullable(); // Foreign key to devices table
            $table->string('entryMethod', 50); // Entry source (Kiosk, Admin, Vacation Rule, etc.)
            $table->timestamp('checkIn')->nullable(); // Exact check-in time
            $table->date('checkInDate')->nullable(); // Date of check-in
            $table->timestamp('checkOut')->nullable(); // Exact check-out time
            $table->float('overtime', 8, 2)->default(0); // Overtime in hours
            $table->boolean('isActive')->default(true); // Soft deletion flag
            $table->boolean('isAltered')->default(false); // Flag to track if record was changed
            $table->unsignedBigInteger('createdBy')->nullable(); // Admin/system who created
            $table->unsignedBigInteger('updatedBy')->nullable(); // Admin/system who updated
            $table->timestamps(); // Created and updated timestamps

            // Foreign key constraints
            $table->foreign('employeeId')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('deviceId')->references('id')->on('devices')->onDelete('set null');
            $table->foreign('createdBy')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updatedBy')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
