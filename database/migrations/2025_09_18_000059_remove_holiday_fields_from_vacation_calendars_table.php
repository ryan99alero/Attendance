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
        Schema::table('vacation_calendars', function (Blueprint $table) {
            // First drop the foreign key constraint
            $table->dropForeign(['holiday_template_id']);

            // Then remove holiday-related columns that were incorrectly added
            $table->dropColumn(['holiday_template_id', 'holiday_type', 'auto_managed', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacation_calendars', function (Blueprint $table) {
            // Add back the columns if needed to rollback
            $table->unsignedBigInteger('holiday_template_id')->nullable();
            $table->string('holiday_type')->nullable();
            $table->boolean('auto_managed')->default(false);
            $table->text('description')->nullable();

            // Re-add the foreign key constraint
            $table->foreign('holiday_template_id')->references('id')->on('holiday_templates')->onDelete('set null');
        });
    }
};
