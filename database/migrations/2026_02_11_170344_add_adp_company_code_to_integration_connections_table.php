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
            // ADP Company Code (3 characters) - used in filename PRcccEPI.csv
            $table->string('adp_company_code', 3)->nullable()->after('export_filename_pattern');

            // Default batch ID format (YYMMDD or sequential)
            $table->string('adp_batch_format', 20)->default('YYMMDD')->after('adp_company_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->dropColumn(['adp_company_code', 'adp_batch_format']);
        });
    }
};
