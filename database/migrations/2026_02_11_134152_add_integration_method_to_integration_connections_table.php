<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            // Integration method: 'api' for real-time API connections, 'flatfile' for file-based exports
            $table->enum('integration_method', ['api', 'flatfile'])
                ->default('api')
                ->after('driver');

            // Make base_url nullable (not needed for flat file integrations)
            $table->string('base_url', 255)->nullable()->change();

            // Add file naming pattern for flat file exports
            $table->string('export_filename_pattern', 255)
                ->nullable()
                ->after('export_path');
        });

        // Set existing ADP connections to 'api' method, can be changed manually
        DB::table('integration_connections')
            ->where('driver', 'adp')
            ->update(['integration_method' => 'api']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->dropColumn('integration_method');
            $table->dropColumn('export_filename_pattern');
            $table->string('base_url', 255)->nullable(false)->change();
        });
    }
};
