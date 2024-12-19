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

        Schema::create('rounding_rules', function (Blueprint $table) {
            $table->id()->comment('Primary key of the rounding_rules table');
            $table->integer('minute_min')->comment('Minimum minute for the rounding range');
            $table->integer('minute_max')->comment('Maximum minute for the rounding range');
            $table->integer('new_minute')->comment('New rounded minute value');
            $table->decimal('new_minute_decimal', 5, 2)->comment('Decimal equivalent of the rounded minute value');
            $table->unsignedBigInteger('round_group_id')->comment('Foreign key to the round_groups table');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key referencing the user who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key referencing the user who last updated the record');
            $table->timestamps()->comment('Timestamps for record creation and updates');

            // Foreign key constraints
            $table->foreign('round_group_id')->references('id')->on('round_groups')->onDelete('cascade')->onUpdate('cascade')->comment('References the round_groups table');
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
        Schema::dropIfExists('rounding_rules');
    }
};
