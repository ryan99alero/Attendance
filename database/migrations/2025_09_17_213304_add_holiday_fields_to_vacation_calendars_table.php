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
            $table->foreignId('holiday_template_id')->nullable()->constrained('holiday_templates')->onDelete('set null');
            $table->enum('holiday_type', ['manual', 'auto_created', 'company_holiday'])->default('manual');
            $table->boolean('auto_managed')->default(false); // True if this is managed by holiday template
            $table->string('description')->nullable(); // Holiday name or description
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacation_calendars', function (Blueprint $table) {
            $table->dropForeign(['holiday_template_id']);
            $table->dropColumn(['holiday_template_id', 'holiday_type', 'auto_managed', 'description']);
        });
    }
};
