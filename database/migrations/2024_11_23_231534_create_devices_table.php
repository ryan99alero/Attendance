<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_name', 100)->comment('Name of the device');
            $table->string('ip_address', 45)->nullable()->comment('IP address of the device');
            $table->boolean('is_active')->default(true)->comment('Indicates if the device is active');
            $table->unsignedBigInteger('department_id')->nullable()->unique()->comment('Foreign key to Departments');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
