<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->string('name', 100)->comment('Human-friendly connection name');
            $table->string('driver', 50)->comment('Integration driver: pace, adp, quickbooks, etc.');
            $table->string('base_url', 255)->comment('API base URL');
            $table->string('api_version', 20)->nullable()->comment('API version if applicable');

            // Authentication
            $table->string('auth_type', 50)->default('basic')->comment('Authentication type: basic, oauth2, api_key');
            $table->text('auth_credentials')->nullable()->comment('Encrypted JSON credentials');

            // Connection settings
            $table->integer('timeout_seconds')->default(30)->comment('Request timeout in seconds');
            $table->integer('retry_attempts')->default(3)->comment('Number of retry attempts on failure');
            $table->integer('rate_limit_per_minute')->nullable()->comment('Max requests per minute');

            // Status
            $table->boolean('is_active')->default(true)->comment('Whether connection is enabled');
            $table->timestamp('last_connected_at')->nullable()->comment('Last successful connection');
            $table->timestamp('last_error_at')->nullable()->comment('Last connection error');
            $table->text('last_error_message')->nullable()->comment('Last error message');

            // Audit
            $table->unsignedBigInteger('created_by')->nullable()->comment('User who created this connection');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('User who last updated');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
