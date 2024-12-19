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
    {Schema::disableForeignKeyConstraints();
        Schema::create('round_groups', function (Blueprint $table) {
            $table->id()->comment('Primary key of the round_groups table');
            $table->string('group_name', 50)->nullable()->unique()->comment('Name of the rounding group (e.g., 5_Minute)');
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        // Drop the table
        Schema::dropIfExists('round_groups');
    }
};
