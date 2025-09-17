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
            // Employment dates
            $table->date('date_of_hire')->nullable()->comment('Employee hire date for vacation accrual calculations');
            $table->date('seniority_date')->nullable()->comment('Date for calculating length of service (may differ from hire date)');

            // Vacation/PTO settings
            $table->decimal('vacation_accrual_rate', 8, 4)->nullable()->comment('Annual vacation hours accrued (e.g., 80.0000 for 2 weeks)');
            $table->decimal('vacation_balance', 8, 2)->default(0)->comment('Current vacation hours balance');
            $table->decimal('vacation_max_carryover', 8, 2)->nullable()->comment('Maximum vacation hours that can carry over to next year');

            // Overtime settings
            $table->boolean('overtime_exempt')->default(false)->comment('True if employee is exempt from overtime (salary)');
            $table->decimal('overtime_rate', 5, 3)->default(1.500)->comment('Overtime multiplier (e.g., 1.500 for time and a half)');
            $table->decimal('double_time_threshold', 8, 2)->nullable()->comment('Hours threshold for double time (e.g., 12.00)');

            // Pay settings
            $table->enum('pay_type', ['hourly', 'salary', 'contract'])->default('hourly')->comment('Employee pay structure');
            $table->decimal('pay_rate', 10, 2)->nullable()->comment('Hourly rate or annual salary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_hire',
                'seniority_date',
                'vacation_accrual_rate',
                'vacation_balance',
                'vacation_max_carryover',
                'overtime_exempt',
                'overtime_rate',
                'double_time_threshold',
                'pay_type',
                'pay_rate'
            ]);
        });
    }
};
