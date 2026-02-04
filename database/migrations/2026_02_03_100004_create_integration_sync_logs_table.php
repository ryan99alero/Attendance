<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_sync_logs', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->unsignedBigInteger('connection_id')->comment('Foreign key to integration_connections');
            $table->unsignedBigInteger('object_id')->nullable()->comment('Foreign key to integration_objects');
            $table->unsignedBigInteger('template_id')->nullable()->comment('Foreign key to integration_query_templates');

            // Operation info
            $table->string('operation', 50)->comment('Operation type: pull, push, test, discover');
            $table->string('status', 50)->comment('Status: pending, running, success, failed, partial');

            // Timing
            $table->timestamp('started_at')->comment('When the sync started');
            $table->timestamp('completed_at')->nullable()->comment('When the sync completed');
            $table->integer('duration_ms')->nullable()->comment('Duration in milliseconds');

            // Results
            $table->integer('records_fetched')->default(0)->comment('Records retrieved from API');
            $table->integer('records_created')->default(0)->comment('New local records created');
            $table->integer('records_updated')->default(0)->comment('Existing records updated');
            $table->integer('records_skipped')->default(0)->comment('Records skipped (no changes)');
            $table->integer('records_failed')->default(0)->comment('Records that failed to process');

            // Request/Response logging
            $table->json('request_payload')->nullable()->comment('The API request sent');
            $table->json('response_summary')->nullable()->comment('Summary of response (not full data)');

            // Error tracking
            $table->text('error_message')->nullable()->comment('Error message if failed');
            $table->json('error_details')->nullable()->comment('Detailed error info (stack trace, etc.)');
            $table->json('failed_records')->nullable()->comment('List of record IDs that failed');

            // Audit
            $table->unsignedBigInteger('triggered_by')->nullable()->comment('User who triggered sync (null = scheduled)');
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('integration_connections')->onDelete('cascade');
            $table->foreign('object_id')->references('id')->on('integration_objects')->onDelete('set null');
            $table->foreign('template_id')->references('id')->on('integration_query_templates')->onDelete('set null');
            $table->foreign('triggered_by')->references('id')->on('users')->onDelete('set null');

            // Index for querying recent syncs
            $table->index(['connection_id', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_logs');
    }
};
