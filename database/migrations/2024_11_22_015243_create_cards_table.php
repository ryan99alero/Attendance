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
        Schema::create('cards', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('employeeId')->nullable(); // Foreign key to employees table
            $table->string('cardIdentifier', 255)->unique(); // Unique card identifier (e.g., RFID code)
            $table->enum('status', ['active', 'inactive', 'lost', 'stolen'])->default('active'); // Card status
            $table->string('deviceAssigned', 255)->nullable(); // Device where card is primarily used
            $table->boolean('isPrimary')->default(false); // Indicates if this is the primary card for the employee
            $table->boolean('isActive')->default(true); // Soft deletion flag
            $table->unsignedBigInteger('createdBy')->nullable(); // Admin/system who created
            $table->unsignedBigInteger('updatedBy')->nullable(); // Admin/system who updated
            $table->timestamps(); // Created and updated timestamps

            // Foreign key constraints
            $table->foreign('employeeId')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('createdBy')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updatedBy')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
