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
            // SMTP Configuration
            $table->boolean('smtp_enabled')->default(false)->after('device_offline_threshold_minutes');
            $table->string('smtp_host')->nullable()->after('smtp_enabled');
            $table->integer('smtp_port')->default(587)->after('smtp_host');
            $table->string('smtp_username')->nullable()->after('smtp_port');
            $table->text('smtp_password')->nullable()->after('smtp_username'); // Encrypted
            $table->enum('smtp_encryption', ['none', 'tls', 'ssl'])->default('tls')->after('smtp_password');
            $table->string('smtp_from_address')->nullable()->after('smtp_encryption');
            $table->string('smtp_from_name')->nullable()->after('smtp_from_address');
            $table->string('smtp_reply_to')->nullable()->after('smtp_from_name');
            $table->integer('smtp_timeout')->default(30)->after('smtp_reply_to');
            $table->boolean('smtp_verify_peer')->default(true)->after('smtp_timeout');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_setup', function (Blueprint $table) {
            $table->dropColumn([
                'smtp_enabled',
                'smtp_host',
                'smtp_port',
                'smtp_username',
                'smtp_password',
                'smtp_encryption',
                'smtp_from_address',
                'smtp_from_name',
                'smtp_reply_to',
                'smtp_timeout',
                'smtp_verify_peer',
            ]);
        });
    }
};
