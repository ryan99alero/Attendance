<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_setup', function (Blueprint $table) {
            $table->integer('log_retention_days')->default(30)->after('logging_level')
                ->comment('Number of days to retain integration sync logs');
            $table->boolean('log_request_payloads')->default(true)->after('log_retention_days')
                ->comment('Whether to log full request payloads');
            $table->boolean('log_response_data')->default(true)->after('log_request_payloads')
                ->comment('Whether to log response summaries');
        });
    }

    public function down(): void
    {
        Schema::table('company_setup', function (Blueprint $table) {
            $table->dropColumn(['log_retention_days', 'log_request_payloads', 'log_response_data']);
        });
    }
};
