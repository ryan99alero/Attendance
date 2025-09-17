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
        Schema::table('employees', function (Blueprint $table) {
            // Remove redundant vacation fields - now handled by vacation system
            $table->dropColumn([
                'vacation_accrual_rate',
                'vacation_balance',
                'vacation_max_carryover',
                'vacation_pay'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Restore vacation fields if needed
            $table->decimal('vacation_accrual_rate', 8, 4)->nullable()->comment('Annual vacation accrual rate');
            $table->decimal('vacation_balance', 8, 2)->default(0)->comment('Current vacation balance in hours');
            $table->decimal('vacation_max_carryover', 8, 2)->nullable()->comment('Maximum vacation hours that can be carried over');
            $table->boolean('vacation_pay')->default(false)->comment('Whether employee receives vacation pay');
        });
    }
};
