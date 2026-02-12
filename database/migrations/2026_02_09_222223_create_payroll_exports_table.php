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
        Schema::create('payroll_exports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('pay_period_id');
            $table->unsignedBigInteger('integration_connection_id');

            $table->string('format', 20)
                ->comment('Export format: csv, xlsx, json, xml, api');

            $table->string('file_path', 500)->nullable()
                ->comment('Path to exported file if saved to filesystem');

            $table->string('file_name', 255)
                ->comment('Generated file name');

            $table->unsignedInteger('employee_count')->default(0)
                ->comment('Number of employees included in export');

            $table->unsignedInteger('record_count')->default(0)
                ->comment('Number of summary records exported');

            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending');

            $table->text('error_message')->nullable()
                ->comment('Error details if status is failed');

            $table->json('metadata')->nullable()
                ->comment('Additional export metadata');

            $table->unsignedBigInteger('exported_by')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('pay_period_id')
                ->references('id')
                ->on('pay_periods')
                ->onDelete('cascade');

            $table->foreign('integration_connection_id')
                ->references('id')
                ->on('integration_connections')
                ->onDelete('cascade');

            $table->foreign('exported_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes
            $table->index(['pay_period_id', 'integration_connection_id']);
            $table->index('status');
            $table->index('exported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_exports');
    }
};
