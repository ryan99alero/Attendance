<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'registered' to the registration_status enum
        DB::statement("ALTER TABLE devices MODIFY COLUMN registration_status ENUM('pending', 'registered', 'approved', 'rejected', 'disabled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'registered' from the enum (revert to original)
        DB::statement("ALTER TABLE devices MODIFY COLUMN registration_status ENUM('pending', 'approved', 'rejected', 'disabled') DEFAULT 'pending'");
    }
};