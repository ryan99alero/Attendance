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
            // Only add fields that don't exist yet

            // ESP32 Configuration fields
            $table->string('device_type', 20)->default('esp32_timeclock')->after('is_active')
                ->comment('Type of device (esp32_timeclock, etc.)');

            $table->json('device_config')->nullable()->after('device_type')
                ->comment('Device-specific configuration (NTP server, pins, etc.)');

            $table->string('api_token', 64)->nullable()->after('device_config')
                ->comment('Authentication token for device API calls');

            $table->timestamp('token_expires_at')->nullable()->after('api_token')
                ->comment('API token expiration time');

            $table->enum('registration_status', ['pending', 'approved', 'rejected', 'disabled'])
                ->default('pending')->after('token_expires_at')
                ->comment('Device registration status');

            $table->text('registration_notes')->nullable()->after('registration_status')
                ->comment('Notes about device registration/approval');

            // Make device_id unique if it isn't already
            $table->unique('device_id');

            // Indexes for performance
            $table->index('mac_address');
            $table->index('last_seen_at');
            $table->index('registration_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Only drop the columns we added
            $table->dropUnique(['device_id']);
            $table->dropIndex(['mac_address']);
            $table->dropIndex(['last_seen_at']);
            $table->dropIndex(['registration_status']);

            $table->dropColumn([
                'device_type',
                'device_config',
                'api_token',
                'token_expires_at',
                'registration_status',
                'registration_notes'
            ]);
        });
    }
};
