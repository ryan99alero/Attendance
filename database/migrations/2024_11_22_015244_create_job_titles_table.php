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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('jobTitle', 255); // Job title or position (e.g., "Software Engineer")
            $table->text('description')->nullable(); // Description of the job
            $table->unsignedBigInteger('departmentId')->nullable(); // Links to a department table if needed
            $table->float('defaultPayRate', 8, 2)->nullable(); // Default hourly or salary rate
            $table->boolean('isActive')->default(true); // Soft deletion flag
            $table->unsignedBigInteger('createdBy')->nullable(); // Admin/system who created
            $table->unsignedBigInteger('updatedBy')->nullable(); // Admin/system who updated
            $table->timestamps(); // Created and updated timestamps

            // Foreign key constraints
            $table->foreign('createdBy')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updatedBy')->references('id')->on('users')->onDelete('set null');
            $table->foreign('departmentId')->references('id')->on('departments')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
