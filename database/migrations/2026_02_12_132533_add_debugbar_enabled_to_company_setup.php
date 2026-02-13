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
        Schema::table('company_setup', function (Blueprint $table) {
            $table->boolean('debugbar_enabled')->default(false)->after('logging_level');
            $table->boolean('telescope_enabled')->default(true)->after('debugbar_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_setup', function (Blueprint $table) {
            $table->dropColumn(['debugbar_enabled', 'telescope_enabled']);
        });
    }
};
