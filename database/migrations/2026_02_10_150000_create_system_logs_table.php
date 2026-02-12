<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();

            // Log classification
            $table->string('category', 50)->comment('Log category: integration, api, system, device, user, error');
            $table->string('type', 100)->comment('Specific log type: sync, request, response, event, action');
            $table->string('level', 20)->default('info')->comment('Log level: debug, info, warning, error, critical');

            // Polymorphic source - what entity generated this log
            $table->nullableMorphs('loggable');

            // Status for operations that have outcomes
            $table->string('status', 50)->nullable()->comment('Operation status: pending, running, success, failed, partial');

            // Summary and description
            $table->string('summary', 500)->comment('Brief description of what happened');
            $table->text('description')->nullable()->comment('Detailed description if needed');

            // Timing for operations
            $table->timestamp('started_at')->nullable()->comment('When operation started');
            $table->timestamp('completed_at')->nullable()->comment('When operation completed');
            $table->integer('duration_ms')->nullable()->comment('Duration in milliseconds');

            // Counts for batch operations
            $table->json('counts')->nullable()->comment('Record counts: fetched, created, updated, skipped, failed');

            // Request/Response for API operations
            $table->json('request_data')->nullable()->comment('Request payload or context');
            $table->json('response_data')->nullable()->comment('Response data or result');

            // Error tracking
            $table->text('error_message')->nullable()->comment('Error message if failed');
            $table->json('error_details')->nullable()->comment('Stack trace, failed records, etc.');

            // Context and metadata
            $table->json('metadata')->nullable()->comment('Additional context-specific data');
            $table->json('tags')->nullable()->comment('Searchable tags for filtering');

            // Audit
            $table->unsignedBigInteger('user_id')->nullable()->comment('User who triggered action');
            $table->string('ip_address', 45)->nullable()->comment('IP address if applicable');
            $table->string('user_agent', 500)->nullable()->comment('User agent if applicable');

            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes for common queries
            $table->index(['category', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['level', 'created_at']);
            $table->index(['status', 'created_at']);
            // Note: nullableMorphs already creates loggable_type + loggable_id index
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
