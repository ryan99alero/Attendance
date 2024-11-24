<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounding_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('Name of the rounding rule');
            $table->integer('minute_min')->nullable()->comment('Minimum minute value for the rounding range');
            $table->integer('minute_max')->nullable()->comment('Maximum minute value for the rounding range');
            $table->integer('new_minute')->nullable()->comment('New minute value after rounding');
            $table->bigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->bigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounding_rules');
    }
};
