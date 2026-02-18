<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ADP Column Mapping:
     * - Column 3 (Hours 3): Double Time (D) - worked special hours
     * - Column 4 (Hours 4): Vacation (V), Holiday (H), Sick (S), Bereavement (F), Jury Duty (J) - time off
     */
    public function up(): void
    {
        Schema::table('classifications', function (Blueprint $table) {
            // adp_column: 3 = Hours 3 (worked special hours), 4 = Hours 4 (time off)
            $table->tinyInteger('adp_column')->nullable()->after('adp_code');
        });

        // Set adp_column based on classification type
        // Hours 4: Time off categories (Vacation, Holiday, Sick, etc.)
        DB::table('classifications')
            ->whereIn('code', ['VACATION', 'HOLIDAY', 'SICK', 'PTO', 'BEREAVEMENT', 'JURY_DUTY', 'PERSONAL'])
            ->update(['adp_column' => 4]);

        // Hours 3: Worked special hours (Double Time, Training)
        DB::table('classifications')
            ->whereIn('code', ['DOUBLE_TIME', 'DOUBLETIME', 'TRAINING'])
            ->update(['adp_column' => 3]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classifications', function (Blueprint $table) {
            $table->dropColumn('adp_column');
        });
    }
};
