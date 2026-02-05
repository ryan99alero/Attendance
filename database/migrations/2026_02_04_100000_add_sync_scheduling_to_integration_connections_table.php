<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->unsignedInteger('sync_interval_minutes')->default(0)->after('rate_limit_per_minute');
            $table->timestamp('last_synced_at')->nullable()->after('sync_interval_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->dropColumn(['sync_interval_minutes', 'last_synced_at']);
        });
    }
};
