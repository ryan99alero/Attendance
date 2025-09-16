<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payroll_frequencies', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('frequency_name', 50)->comment('Display name (e.g., "Bi-Weekly", "Semi-Monthly")');
            $table->enum('frequency_type', ['weekly', 'biweekly', 'semimonthly', 'monthly', 'quarterly', 'annually'])
                ->comment('System type for calculation logic');

            // Reference Dates (Critical for Bi-weekly and other moving cycles)
            $table->date('reference_start_date')->nullable()
                ->comment('Starting date for bi-weekly cycles or other recurring calculations');

            // Weekly Configuration
            $table->integer('weekly_day')->nullable()
                ->comment('Day of week for weekly/bi-weekly pay (0=Sunday, 6=Saturday)');

            // Monthly/Semi-monthly Configuration
            $table->integer('first_pay_day')->nullable()
                ->comment('First pay day of month (1-31, or special values: 99=last_day)');
            $table->integer('second_pay_day')->nullable()
                ->comment('Second pay day of month for semi-monthly (1-31, or special values: 99=last_day)');

            // Quarterly/Annual Configuration
            $table->integer('pay_month_1')->nullable()->comment('First pay month for quarterly/annual (1-12)');
            $table->integer('pay_month_2')->nullable()->comment('Second pay month for quarterly (1-12)');
            $table->integer('pay_month_3')->nullable()->comment('Third pay month for quarterly (1-12)');
            $table->integer('pay_month_4')->nullable()->comment('Fourth pay month for quarterly (1-12)');
            $table->integer('annual_pay_day')->nullable()->comment('Day of year for annual pay (1-365)');

            // Business Rule Adjustments
            $table->enum('month_end_handling', ['exact_day', 'last_day_of_month', 'first_day_next_month'])
                ->default('exact_day')
                ->comment('How to handle when pay day > days in month');
            $table->enum('weekend_adjustment', ['none', 'previous_friday', 'next_monday', 'closest_weekday'])
                ->default('none')
                ->comment('How to adjust pay dates that fall on weekends');
            $table->boolean('skip_holidays')->default(false)
                ->comment('Whether to adjust pay dates that fall on company holidays');

            // Period Configuration
            $table->integer('period_length_days')->nullable()
                ->comment('Length of pay period in days (calculated field for reference)');

            // Metadata
            $table->text('description')->nullable()
                ->comment('Human readable description of this frequency configuration');
            $table->boolean('is_active')->default(true)
                ->comment('Whether this frequency is available for selection');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index('frequency_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_frequencies');
    }
};
