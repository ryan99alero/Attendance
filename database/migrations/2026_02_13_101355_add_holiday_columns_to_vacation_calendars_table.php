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
            $table->foreignId('holiday_template_id')->nullable()->after('is_recorded')
                ->constrained('holiday_templates')->nullOnDelete();
            $table->string('holiday_type', 50)->nullable()->after('holiday_template_id');
            $table->boolean('auto_managed')->default(false)->after('holiday_type');
            $table->string('description')->nullable()->after('auto_managed');
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
