<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_exports', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0)->after('status')
                ->comment('Progress percentage 0-100');
            $table->string('progress_message')->nullable()->after('progress')
                ->comment('Current step description');
            $table->unsignedInteger('total_employees')->nullable()->after('progress_message')
                ->comment('Total employees to process');
            $table->unsignedInteger('processed_employees')->default(0)->after('total_employees')
                ->comment('Employees processed so far');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_exports', function (Blueprint $table) {
            $table->dropColumn(['progress', 'progress_message', 'total_employees', 'processed_employees']);
        });
    }
};
