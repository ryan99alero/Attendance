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

        Schema::create('overtime_rules', function (Blueprint $table) {
            $table->id()->comment('Primary key of the overtime_rules table');
            $table->string('rule_name', 100)->comment('Name of the overtime rule');
            $table->integer('hours_threshold')->default(40)->comment('Minimum hours worked per week to trigger overtime calculation');
            $table->decimal('multiplier', 5, 2)->default(1.50)->comment('Multiplier for overtime pay');
            $table->unsignedBigInteger('shift_id')->nullable()->comment('Foreign key to shifts table, representing the shift this rule applies to');
            $table->integer('consecutive_days_threshold')->nullable()->comment('Number of consecutive days required to trigger this rule');
            $table->boolean('applies_on_weekends')->default(false)->comment('Indicates if the rule applies to work done on weekends');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps()->comment('Timestamps for record creation and last update');

            // Indexes for optimization
            $table->index('hours_threshold', 'idx_hours_threshold')->comment('Index for queries involving hours threshold');
            $table->index('shift_id', 'idx_shift_id')->comment('Index for optimizing queries on the shift_id field');

            // Foreign key constraints
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null')->comment('References the shifts table');
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
        Schema::dropIfExists('overtime_rules');
    }
};
