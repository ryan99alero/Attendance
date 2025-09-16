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
        Schema::create('clock_events', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('cascade')->onUpdate('cascade')
                  ->comment('FK -> employees.id (who performed the event)');
            $table->foreignId('device_id')->nullable()->constrained('devices')->onDelete('set null')->onUpdate('cascade')
                  ->comment('FK -> devices.id (which device saw it)');
            $table->foreignId('credential_id')->nullable()->constrained('credentials')->onDelete('set null')->onUpdate('cascade')
                  ->comment('FK -> credentials.id (which credential was used, if any)');
            $table->foreignId('punch_type_id')->nullable()->constrained('punch_types')->onDelete('set null')->onUpdate('cascade')
                  ->comment('FK -> punch_types.id (Clock In, Clock Out, etc.)');
            $table->dateTime('event_time')->comment('Exact server-side timestamp the event was recorded');
            $table->date('shift_date')->nullable()->comment('Logical workday the event belongs to (app assigned)');
            $table->enum('event_source', ['device', 'api', 'backfill', 'admin'])->default('device')
                  ->comment('How this event was recorded');
            $table->string('location', 191)->nullable()->comment('Optional freeform location label from device');
            $table->tinyInteger('confidence')->unsigned()->nullable()->comment('0–100 confidence score (e.g., biometric/NFC quality)');
            $table->json('raw_payload')->nullable()->comment('Optional raw payload for audit/debug');
            $table->string('notes', 255)->nullable()->comment('Short operator/system note');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')
                  ->comment('Users.id that created the record (if admin/API)');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')
                  ->comment('Users.id that last updated the record');
            $table->timestamps();

            // Unique constraint to prevent exact duplicates
            $table->unique(['employee_id', 'event_time', 'device_id'], 'uniq_emp_event_device');

            // Indexes for performance
            $table->index(['employee_id', 'event_time'], 'idx_clock_events_employee_time');
            $table->index(['device_id', 'event_time'], 'idx_clock_events_device_time');
            $table->index('shift_date', 'idx_clock_events_shift_date');
            $table->index('punch_type_id', 'idx_clock_events_punch_type');
            $table->index(['employee_id', 'shift_date', 'punch_type_id'], 'idx_clock_events_employee_shift_type');
            $table->index(['credential_id', 'event_time'], 'idx_clock_events_credential_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clock_events');
    }
};