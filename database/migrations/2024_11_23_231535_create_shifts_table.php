<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_name', 100)->comment('Name of the shift');
            $table->time('start_time')->comment('Scheduled start time of the shift');
            $table->time('end_time')->comment('Scheduled end time of the shift');
            $table->smallInteger('base_hours_per_period')->nullable()->comment('Standard hours for the shift per pay period');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
