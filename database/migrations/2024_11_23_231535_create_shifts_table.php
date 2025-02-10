<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::disableForeignKeyConstraints();

        Schema::create('shifts', function (Blueprint $table) {
            $table->id()->comment('Primary key of the shifts table');

            // Shift details
            $table->string('shift_name', 100)->comment('Name of the shift');
            $table->time('start_time')->comment('Scheduled start time of the shift');
            $table->time('end_time')->comment('Scheduled end time of the shift');
            $table->smallInteger('base_hours_per_period')->nullable()->comment('Standard hours for the shift per pay period');
            $table->boolean('multi_day_shift')->default(false)->comment('Indicates if the shift spans multiple calendar days');

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
