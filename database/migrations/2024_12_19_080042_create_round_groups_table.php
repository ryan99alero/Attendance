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
        Schema::create('round_groups', function (Blueprint $table) {
            $table->id()->comment('Primary key of the round_groups table');
            $table->string('group_name', 20)->unique()->comment('Name of the rounding group (e.g., 5_minute)');
            $table->timestamps()->comment('Timestamps for record creation and updates');

            // Indexes
            $table->unique('group_name', 'idx_unique_group_name')->comment('Ensures group_name is unique for each rounding group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_groups');
    }
};
