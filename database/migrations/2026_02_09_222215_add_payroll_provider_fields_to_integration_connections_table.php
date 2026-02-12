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
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->boolean('is_payroll_provider')->default(false)->after('is_active')
                ->comment('Marks this connection as a payroll export destination');

            $table->json('export_formats')->nullable()->after('is_payroll_provider')
                ->comment('Array of enabled export formats: csv, xlsx, json, xml, api');

            $table->enum('export_destination', ['download', 'path'])->nullable()->after('export_formats')
                ->comment('Where to save exports: download or file path');

            $table->string('export_path', 500)->nullable()->after('export_destination')
                ->comment('Server path for file exports when destination is path');

            $table->index('is_payroll_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->dropIndex(['is_payroll_provider']);
            $table->dropColumn([
                'is_payroll_provider',
                'export_formats',
                'export_destination',
                'export_path',
            ]);
        });
    }
};
