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
        Schema::create('payPeriods', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->date('startDate'); // Start date of the pay period
            $table->date('endDate'); // End date of the pay period
            $table->boolean('isClosed')->default(false); // Indicates if the pay period is closed
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
        Schema::dropIfExists('payPeriods');
    }
};
