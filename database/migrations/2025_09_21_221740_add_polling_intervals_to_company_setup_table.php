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
        Schema::table('company_setup', function (Blueprint $table) {
            // Configuration polling intervals for device management
            $table->integer('config_poll_interval_minutes')->default(5)->after('updated_at')
                  ->comment('How often devices should poll for configuration updates (in minutes)');

            $table->integer('firmware_check_interval_hours')->default(24)->after('config_poll_interval_minutes')
                  ->comment('How often devices should check for firmware updates (in hours)');

            // Allow individual device overrides
            $table->boolean('allow_device_poll_override')->default(false)->after('firmware_check_interval_hours')
                  ->comment('Allow individual devices to override company polling settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_setup', function (Blueprint $table) {
            $table->dropColumn([
                'config_poll_interval_minutes',
                'firmware_check_interval_hours',
                'allow_device_poll_override'
            ]);
        });
    }
};
