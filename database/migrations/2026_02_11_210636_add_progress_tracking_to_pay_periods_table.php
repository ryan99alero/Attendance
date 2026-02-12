<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_periods', function (Blueprint $table) {
            $table->unsignedTinyInteger('processing_progress')->default(0)->after('processing_status')
                ->comment('Processing progress 0-100');
            $table->string('processing_message')->nullable()->after('processing_progress')
                ->comment('Current processing step message');
            $table->unsignedInteger('total_employees')->nullable()->after('processing_message')
                ->comment('Total employees to process');
            $table->unsignedInteger('processed_employees')->default(0)->after('total_employees')
                ->comment('Employees processed so far');
        });
    }

    public function down(): void
    {
        Schema::table('pay_periods', function (Blueprint $table) {
            $table->dropColumn([
                'processing_progress',
                'processing_message',
                'total_employees',
                'processed_employees',
            ]);
        });
    }
};
