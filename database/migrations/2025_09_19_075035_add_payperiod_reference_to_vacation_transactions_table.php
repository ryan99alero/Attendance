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
        Schema::table('vacation_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('pay_period_id')->nullable()->after('accrual_period');
            $table->unsignedBigInteger('reference_id')->nullable()->after('pay_period_id');
            $table->string('reference_type')->nullable()->after('reference_id');

            // Add foreign key constraint
            $table->foreign('pay_period_id')->references('id')->on('pay_periods')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacation_transactions', function (Blueprint $table) {
            $table->dropForeign(['pay_period_id']);
            $table->dropColumn(['pay_period_id', 'reference_id', 'reference_type']);
        });
    }
};
