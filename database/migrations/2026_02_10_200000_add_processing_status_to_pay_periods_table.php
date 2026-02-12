<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_periods', function (Blueprint $table) {
            $table->string('processing_status', 20)->nullable()->after('is_posted')
                ->comment('Job status: null, processing, completed, failed');
            $table->text('processing_error')->nullable()->after('processing_status');
            $table->timestamp('processing_started_at')->nullable()->after('processing_error');
            $table->timestamp('processing_completed_at')->nullable()->after('processing_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('pay_periods', function (Blueprint $table) {
            $table->dropColumn(['processing_status', 'processing_error', 'processing_started_at', 'processing_completed_at']);
        });
    }
};
