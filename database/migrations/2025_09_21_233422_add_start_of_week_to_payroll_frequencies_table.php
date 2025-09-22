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
        Schema::table('payroll_frequencies', function (Blueprint $table) {
            $table->integer('start_of_week')->default(0)->after('weekly_day')
                  ->comment('Start of work week (0=Sunday, 1=Monday, 2=Tuesday, etc.)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_frequencies', function (Blueprint $table) {
            $table->dropColumn('start_of_week');
        });
    }
};
