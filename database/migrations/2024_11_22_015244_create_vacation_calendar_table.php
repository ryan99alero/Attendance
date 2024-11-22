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
        Schema::create('vacations', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('employeeId'); // Links to the employee
            $table->date('vacationDate'); // Specific vacation date
            $table->float('hoursUsed', 5, 2)->default(0); // Hours of vacation used on this date
            $table->float('hoursAccrued', 5, 2)->default(0); // Hours of vacation accrued on this date
            $table->string('reason', 255)->nullable(); // Reason for vacation (optional)
            $table->unsignedBigInteger('createdBy')->nullable(); // Admin/system who created
            $table->unsignedBigInteger('updatedBy')->nullable(); // Admin/system who updated
            $table->boolean('isActive')->default(true); // Soft deletion flag
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
        Schema::dropIfExists('vacations');
    }
};
