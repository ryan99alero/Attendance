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
        Schema::create('classifications', function (Blueprint $table) {
            $table->id()->comment('Primary key of the classifications table');
            $table->string('name')->unique()->comment('Name of the classification (e.g., Holiday, Vacation)');
            $table->string('code', 50)->unique()->comment('Unique code identifier (e.g., HOLIDAY, VACATION)');
            $table->text('description')->nullable()->comment('Detailed description of the classification');
            $table->boolean('is_active')->default(true)->comment('Indicates if the classification is active');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for the record creator');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for the last updater');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classifications');
    }
};
