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
        Schema::create('overtime_calculation_logs', function (Blueprint $table) {
            $table->id();

            // Link to employee
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade')
                ->comment('The employee this calculation is for');

            // Link to pay period
            $table->foreignId('pay_period_id')
                ->constrained('pay_periods')
                ->onDelete('cascade')
                ->comment('The pay period this calculation belongs to');

            // The specific date this calculation is for
            $table->date('work_date')
                ->comment('The specific work date this calculation covers');

            // Hours breakdown
            $table->decimal('total_hours_worked', 6, 2)->default(0)
                ->comment('Total hours worked on this date');

            $table->decimal('regular_hours', 6, 2)->default(0)
                ->comment('Hours at regular pay rate');

            $table->decimal('overtime_hours', 6, 2)->default(0)
                ->comment('Hours at overtime pay rate (1.5x)');

            $table->decimal('double_time_hours', 6, 2)->default(0)
                ->comment('Hours at double-time pay rate (2.0x)');

            $table->decimal('holiday_hours', 6, 2)->default(0)
                ->comment('Hours at holiday pay rate');

            // Which rule was applied
            $table->foreignId('overtime_rule_id')->nullable()
                ->constrained('overtime_rules')
                ->onDelete('set null')
                ->comment('The overtime rule that was applied');

            $table->foreignId('holiday_instance_id')->nullable()
                ->constrained('holiday_instances')
                ->onDelete('set null')
                ->comment('The holiday instance if this was a holiday');

            // Reason for the calculation result
            $table->string('calculation_reason', 255)->nullable()
                ->comment('Human-readable reason for this calculation result');

            // Additional context stored as JSON
            $table->json('calculation_context')->nullable()
                ->comment('Additional context: consecutive days, prior day worked, etc.');

            // Multipliers applied
            $table->decimal('overtime_multiplier', 5, 2)->nullable()
                ->comment('The overtime multiplier used (e.g., 1.5)');

            $table->decimal('double_time_multiplier', 5, 2)->nullable()
                ->comment('The double-time multiplier used (e.g., 2.0)');

            // Whether this log entry has been finalized
            $table->boolean('is_finalized')->default(false)
                ->comment('Whether this calculation has been finalized');

            // Who/what created this log
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('User who triggered the calculation, if applicable');

            $table->timestamps();

            // Unique constraint: one log per employee per date per pay period
            $table->unique(['employee_id', 'pay_period_id', 'work_date'], 'unique_employee_period_date');

            // Index for pay period lookups
            $table->index('pay_period_id', 'idx_pay_period');

            // Index for employee + date range queries
            $table->index(['employee_id', 'work_date'], 'idx_employee_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_calculation_logs');
    }
};
