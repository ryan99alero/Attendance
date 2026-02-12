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
        Schema::create('holiday_instances', function (Blueprint $table) {
            $table->id();

            // Link to the template that generated this instance
            $table->foreignId('holiday_template_id')
                ->constrained('holiday_templates')
                ->onDelete('cascade')
                ->comment('The template this holiday instance was generated from');

            // The actual date of this holiday
            $table->date('holiday_date')
                ->comment('The actual date of this holiday instance');

            // The year this instance applies to
            $table->year('year')
                ->comment('The year this holiday instance is for');

            // Resolved name (may include year-specific variations)
            $table->string('name')
                ->comment('The display name for this holiday instance');

            // Holiday pay multiplier (inherited from template but can be overridden)
            $table->decimal('holiday_multiplier', 5, 2)->default(2.00)
                ->comment('Pay multiplier for this holiday instance');

            // Standard hours for this holiday
            $table->decimal('standard_hours', 4, 2)->default(8.00)
                ->comment('Standard hours credited for this holiday');

            // Eligibility requirements (inherited from template but can be overridden)
            $table->boolean('require_day_before')->default(false)
                ->comment('Must work day before to qualify');

            $table->boolean('require_day_after')->default(false)
                ->comment('Must work day after to qualify');

            $table->boolean('paid_if_not_worked')->default(true)
                ->comment('Paid even if not worked');

            // Eligible pay types (inherited from template)
            $table->json('eligible_pay_types')->nullable()
                ->comment('Pay types eligible for this holiday');

            // Whether this instance is active
            $table->boolean('is_active')->default(true)
                ->comment('Whether this holiday instance is active');

            // Optional notes for this specific instance
            $table->text('notes')->nullable()
                ->comment('Administrative notes for this holiday instance');

            $table->timestamps();

            // Unique constraint: one instance per template per year
            $table->unique(['holiday_template_id', 'year'], 'unique_template_year');

            // Index for date lookups
            $table->index('holiday_date', 'idx_holiday_date');

            // Index for year + active lookups
            $table->index(['year', 'is_active'], 'idx_year_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday_instances');
    }
};
