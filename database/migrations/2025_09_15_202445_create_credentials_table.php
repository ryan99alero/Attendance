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
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->enum('kind', ['rfid', 'nfc', 'magstripe', 'qrcode', 'barcode', 'ble', 'biometric', 'pin', 'mobile'])
                  ->comment('Credential technology / method');
            $table->string('identifier', 255)->nullable()
                  ->comment('Raw identifier when safe to store (e.g., RFID/NFC UID, QR text). NULL for secrets like PIN/biometric.');
            $table->binary('identifier_hash')->nullable()
                  ->comment('Hash of sensitive value (e.g., PIN). Use bcrypt/argon2. NULL when identifier is stored in plaintext.');
            $table->enum('hash_algo', ['bcrypt', 'argon2id', 'argon2i'])->nullable()
                  ->comment('Which algorithm produced identifier_hash (if used).');
            $table->string('template_ref', 255)->nullable()
                  ->comment('Pointer to biometric template in secure store (do NOT store raw template here).');
            $table->binary('template_hash')->nullable()
                  ->comment('Hash of the template bytes for integrity/versioning, not reversible.');
            $table->string('label', 100)->nullable()
                  ->comment('Friendly display label, e.g., "Blue HID fob"');
            $table->boolean('is_active')->default(true)
                  ->comment('1=usable, 0=revoked/disabled');
            $table->timestamp('issued_at')->nullable()
                  ->comment('When this credential was issued to the employee');
            $table->timestamp('revoked_at')->nullable()
                  ->comment('When disabled; keep row for history linkage');
            $table->timestamp('last_used_at')->nullable()
                  ->comment('Most recent successful use');
            $table->json('metadata')->nullable()
                  ->comment('Optional extra fields from device/provisioning (e.g., ATR, facility code)');
            $table->timestamps();

            // Unique constraints
            $table->unique(['kind', 'identifier'], 'uq_credentials_kind_identifier');
            $table->unique(['kind', 'identifier_hash'], 'uq_credentials_kind_identifier_hash');

            // Indexes
            $table->index('employee_id', 'idx_credentials_employee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};