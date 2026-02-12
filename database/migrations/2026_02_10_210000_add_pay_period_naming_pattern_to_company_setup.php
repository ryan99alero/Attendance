<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_setup', function (Blueprint $table) {
            $table->string('pay_period_naming_pattern', 255)
                ->nullable()
                ->default('Week {week_number}, {year}')
                ->after('payroll_start_date')
                ->comment('Pattern for auto-naming pay periods');
        });
    }

    public function down(): void
    {
        Schema::table('company_setup', function (Blueprint $table) {
            $table->dropColumn('pay_period_naming_pattern');
        });
    }
};
