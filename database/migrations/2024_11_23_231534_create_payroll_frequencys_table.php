<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('payroll_frequencies', function (Blueprint $table) {
            $table->id();
            $table->string('frequency_name', 50)->comment('Name of the payroll frequency');
            $table->integer('weekly_day')->nullable()->comment('Day of the week for weekly payroll (0-6, Sun-Sat)');
            $table->integer('semimonthly_first_day')->nullable()->comment('First fixed day of the month for semimonthly payroll');
            $table->integer('semimonthly_second_day')->nullable()->comment('Second fixed day of the month for semimonthly payroll');
            $table->integer('monthly_day')->nullable()->comment('Day of the month for monthly payroll');
            $table->bigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->bigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_frequencies');
    }
};
