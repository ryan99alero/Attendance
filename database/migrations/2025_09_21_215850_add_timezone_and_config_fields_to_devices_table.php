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
        Schema::table('devices', function (Blueprint $table) {
            // Timezone configuration for the device
            $table->string('timezone', 50)->nullable()->after('device_config')
                  ->comment('Device timezone (e.g., America/Chicago, UTC-5)');

            // Configuration sync tracking
            $table->timestamp('config_updated_at')->nullable()->after('timezone')
                  ->comment('When device configuration was last updated from server');

            $table->timestamp('config_synced_at')->nullable()->after('config_updated_at')
                  ->comment('When device last synced configuration from server');

            // Enhanced device identification
            $table->string('display_name', 100)->nullable()->after('device_name')
                  ->comment('Human-friendly device name (e.g., Front Office Clock)');

            // Configuration version for tracking updates
            $table->integer('config_version')->default(1)->after('config_synced_at')
                  ->comment('Configuration version number for sync tracking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'config_updated_at',
                'config_synced_at',
                'display_name',
                'config_version'
            ]);
        });
    }
};
