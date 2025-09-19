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
        // First drop the foreign key constraint from attendances table
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['holiday_id']);
        });

        // Now we can safely drop the holidays table
        Schema::dropIfExists('holidays');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot recreate the old holidays table structure in down()
        // This migration is irreversible since data has been migrated
        throw new Exception('Cannot rollback dropping holidays table - data has been migrated to holiday_templates');
    }
};
