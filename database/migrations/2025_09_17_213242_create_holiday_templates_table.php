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
        Schema::create('holiday_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "New Year's Day", "Christmas", "Independence Day"
            $table->enum('type', ['fixed_date', 'relative', 'custom']); // How the date is calculated
            $table->json('calculation_rule'); // Rules for calculating the date
            $table->integer('auto_create_days_ahead')->default(365); // Create holidays this many days in advance
            $table->boolean('applies_to_all_employees')->default(true); // Apply to all employees or specific groups
            $table->boolean('is_active')->default(true); // Enable/disable this holiday template
            $table->text('description')->nullable(); // Additional details about the holiday
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday_templates');
    }
};
