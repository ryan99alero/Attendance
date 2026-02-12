<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->string('model_type')->comment('The model class being imported');
            $table->string('original_file_name')->comment('Original uploaded file name');
            $table->string('file_path')->nullable()->comment('Storage path to the import file');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedTinyInteger('progress')->default(0)->comment('Progress 0-100');
            $table->string('progress_message')->nullable()->comment('Current step message');
            $table->unsignedInteger('total_rows')->nullable()->comment('Total rows to import');
            $table->unsignedInteger('processed_rows')->default(0)->comment('Rows processed');
            $table->unsignedInteger('successful_rows')->default(0)->comment('Rows imported successfully');
            $table->unsignedInteger('failed_rows')->default(0)->comment('Rows that failed');
            $table->string('error_file_path')->nullable()->comment('Path to error export file');
            $table->text('error_message')->nullable()->comment('Error message if failed');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('imported_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_imports');
    }
};
