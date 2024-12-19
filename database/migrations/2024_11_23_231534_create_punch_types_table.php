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

        Schema::create('punch_types', function (Blueprint $table) {
            $table->id()->comment('Primary key of the punch_types table');
            $table->string('name', 100)->comment('Name of the punch type (e.g., Clock In, Clock Out)');
            $table->text('description')->nullable()->comment('Detailed description of the punch type');
            $table->enum('schedule_reference', [
                'start_time',
                'lunch_start',
                'lunch_stop',
                'stop_time',
                'break_start',
                'break_stop',
                'manual',
                'jury_duty',
                'bereavement',
                'flexible',
                'passthrough'
            ])->nullable()->comment('Reference to a schedule event associated with this punch type');
            $table->boolean('is_active')->default(true)->comment('Indicates if the punch type is currently active');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps();

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
        Schema::dropIfExists('punch_types');
    }
};
