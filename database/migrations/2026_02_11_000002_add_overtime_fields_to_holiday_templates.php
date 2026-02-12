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
        Schema::table('holiday_templates', function (Blueprint $table) {
            // Holiday pay multiplier (default 2.0x for double-time on holidays)
            $table->decimal('holiday_multiplier', 5, 2)->default(2.00)->after('is_active')
                ->comment('Pay multiplier for hours worked on this holiday (default 2.0x)');

            // Requirement to work the day before to qualify for holiday pay
            $table->boolean('require_day_before')->default(false)->after('holiday_multiplier')
                ->comment('Employee must work the day before the holiday to receive holiday pay');

            // Requirement to work the day after to qualify for holiday pay
            $table->boolean('require_day_after')->default(false)->after('require_day_before')
                ->comment('Employee must work the day after the holiday to receive holiday pay');

            // Whether employees get paid if they don't work the holiday
            $table->boolean('paid_if_not_worked')->default(true)->after('require_day_after')
                ->comment('Whether employees receive holiday pay even if they do not work on the holiday');

            // Standard hours credited for the holiday (default 8 hours)
            $table->decimal('standard_holiday_hours', 4, 2)->default(8.00)->after('paid_if_not_worked')
                ->comment('Standard hours to credit for this holiday (default 8 hours)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holiday_templates', function (Blueprint $table) {
            $table->dropColumn([
                'holiday_multiplier',
                'require_day_before',
                'require_day_after',
                'paid_if_not_worked',
                'standard_holiday_hours',
            ]);
        });
    }
};
