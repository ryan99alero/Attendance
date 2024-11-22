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
        Schema::create('devices', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('deviceName', 255)->nullable(); // Name of the device
            $table->string('deviceType', 50)->nullable(); // Type of device (e.g., Kiosk, Mobile, Web App)
            $table->string('serialNumber', 255)->nullable(); // Device serial number
            $table->ipAddress('ipAddress')->nullable(); // IP address of the device
            $table->string('location', 255)->nullable(); // Physical location of the device
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
        Schema::dropIfExists('devices');
    }
};
