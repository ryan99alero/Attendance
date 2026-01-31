<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds fields for device offline alerting:
     * - devices.offline_alerted_at: When alert was sent (null = not alerted/online)
     * - company_setup.device_alert_email: Company-wide alert recipient
     * - company_setup.device_offline_threshold_minutes: Minutes before alerting
     */
    public function up(): void
    {
        // Add offline alert tracking to devices
        Schema::table('devices', function (Blueprint $table) {
            $table->timestamp('offline_alerted_at')->nullable()->after('last_seen_at')
                ->comment('When offline alert was sent. Null = online/not alerted');
        });

        // Add alert settings to company_setup
        Schema::table('company_setup', function (Blueprint $table) {
            $table->string('device_alert_email', 255)->nullable()->after('allow_device_poll_override')
                ->comment('Email address for device offline alerts');
            $table->integer('device_offline_threshold_minutes')->default(5)->after('device_alert_email')
                ->comment('Minutes of no heartbeat before sending offline alert');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('offline_alerted_at');
        });

        Schema::table('company_setup', function (Blueprint $table) {
            $table->dropColumn(['device_alert_email', 'device_offline_threshold_minutes']);
        });
    }
};
