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
        Schema::disableForeignKeyConstraints();

        Schema::create('holidays', function (Blueprint $table) {
            $table->id()->comment('Primary key of the holidays table');
            $table->string('name', 100)->comment('Name of the holiday');
            $table->date('start_date')->nullable()->comment('Start date of the holiday');
            $table->date('end_date')->nullable()->comment('End date of the holiday, if applicable');
            $table->boolean('is_recurring')->default(false)->comment('Indicates if the holiday recurs annually');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps()->comment('Timestamps for record creation and last update');

            // Indexes for optimization
            $table->index(['start_date', 'end_date'], 'idx_holiday_dates')->comment('Index for optimizing queries on holiday date range');

            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for the record creator');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null')->comment('References the users table for the last updater');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
