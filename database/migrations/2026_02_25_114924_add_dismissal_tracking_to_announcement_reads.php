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
        Schema::table('announcement_reads', function (Blueprint $table) {
            // Track when recipient dismisses without acknowledging
            $table->timestamp('dismissed_at')->nullable()->after('acknowledged_at');

            // Index for querying dismissed records
            $table->index(['announcement_id', 'dismissed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcement_reads', function (Blueprint $table) {
            $table->dropIndex(['announcement_id', 'dismissed_at']);
            $table->dropColumn('dismissed_at');
        });
    }
};
