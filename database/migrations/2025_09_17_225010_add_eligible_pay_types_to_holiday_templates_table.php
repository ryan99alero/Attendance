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
        Schema::table('holiday_templates', function (Blueprint $table) {
            $table->json('eligible_pay_types')->nullable()->after('applies_to_all_employees');
        });

        // Set default values for existing records
        DB::table('holiday_templates')->update([
            'eligible_pay_types' => '["salary", "hourly_fulltime"]'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holiday_templates', function (Blueprint $table) {
            $table->dropColumn('eligible_pay_types');
        });
    }
};
