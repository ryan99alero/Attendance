<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_field_mappings', function (Blueprint $table) {
            $table->string('local_table', 100)->nullable()->after('object_id');
        });
    }

    public function down(): void
    {
        Schema::table('integration_field_mappings', function (Blueprint $table) {
            $table->dropColumn('local_table');
        });
    }
};
