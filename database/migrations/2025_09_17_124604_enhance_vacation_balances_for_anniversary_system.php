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
        Schema::table('vacation_balances', function (Blueprint $table) {
            // Anniversary-based accrual system fields
            $table->integer('accrual_year')->default(1)->comment('Current accrual year (1st year, 2nd year, etc.)');
            $table->date('last_anniversary_date')->nullable()->comment('Date of last anniversary when vacation was credited');
            $table->date('next_anniversary_date')->nullable()->comment('Date of next anniversary for vacation credit');
            $table->decimal('annual_days_earned', 5, 2)->default(0)->comment('Days earned this accrual year');

            // Historical tracking
            $table->decimal('previous_year_balance', 8, 2)->default(0)->comment('Balance from previous accrual year');
            $table->decimal('current_year_awarded', 8, 2)->default(0)->comment('Hours awarded on most recent anniversary');
            $table->decimal('current_year_used', 8, 2)->default(0)->comment('Hours used in current accrual year');

            // Policy and status
            $table->boolean('is_anniversary_based')->default(true)->comment('Uses anniversary-based accrual vs continuous');
            $table->json('accrual_history')->nullable()->comment('JSON log of anniversary awards and usage');
            $table->date('policy_effective_date')->nullable()->comment('Date this accrual policy became effective');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacation_balances', function (Blueprint $table) {
            $table->dropColumn([
                'accrual_year',
                'last_anniversary_date',
                'next_anniversary_date',
                'annual_days_earned',
                'previous_year_balance',
                'current_year_awarded',
                'current_year_used',
                'is_anniversary_based',
                'accrual_history',
                'policy_effective_date'
            ]);
        });
    }
};
