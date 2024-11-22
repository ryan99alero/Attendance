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
        Schema::create('overtime', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('employeeId'); // Links to the employee
            $table->date('overtimeDate'); // Date overtime was worked
            $table->float('hours', 5, 2); // Number of overtime hours worked
            $table->string('reason', 255)->nullable(); // Optional reason for overtime
            $table->boolean('isApproved')->default(false); // Whether the overtime was approved
            $table->unsignedBigInteger('approvedBy')->nullable(); // Who approved the overtime
            $table->unsignedBigInteger('createdBy')->nullable(); // Who created the record
            $table->unsignedBigInteger('updatedBy')->nullable(); // Who updated the record
            $table->boolean('isActive')->default(true); // Soft deletion flag
            $table->timestamps(); // Created and updated timestamps

            // Foreign key constraints
            $table->foreign('employeeId')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('approvedBy')->references('id')->on('users')->onDelete('set null');
            $table->foreign('createdBy')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updatedBy')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime');
    }
};
