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
        Schema::table('clock_events', function (Blueprint $table) {
            $table->boolean('is_processed')->default(false)->after('raw_payload');
            $table->timestamp('processed_at')->nullable()->after('is_processed');
            $table->unsignedBigInteger('attendance_id')->nullable()->after('processed_at');
            $table->string('batch_id')->nullable()->after('attendance_id');
            $table->text('processing_error')->nullable()->after('batch_id');

            // Add foreign key constraint
            $table->foreign('attendance_id')->references('id')->on('attendances')->onDelete('set null');

            // Add indexes for performance
            $table->index('is_processed');
            $table->index(['employee_id', 'shift_date', 'is_processed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            $table->dropForeign(['attendance_id']);
            $table->dropIndex(['employee_id', 'shift_date', 'is_processed']);
            $table->dropIndex(['is_processed']);
            $table->dropColumn(['is_processed', 'processed_at', 'attendance_id', 'batch_id', 'processing_error']);
        });
    }
};
