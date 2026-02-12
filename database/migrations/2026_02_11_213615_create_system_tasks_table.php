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
        Schema::create('system_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index(); // 'import', 'export', 'processing', 'sync'
            $table->string('name'); // Human readable: "Employee Import", "Pay Period Processing"
            $table->string('description')->nullable(); // Details about what's being processed
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending')->index();
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->string('progress_message')->nullable();
            $table->unsignedInteger('total_records')->nullable();
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('successful_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->string('related_model')->nullable(); // e.g., "App\Models\Employee"
            $table->unsignedBigInteger('related_id')->nullable(); // e.g., PayPeriod ID
            $table->string('file_path')->nullable(); // Input file for imports
            $table->string('output_file_path')->nullable(); // Output file for exports
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_tasks');
    }
};
