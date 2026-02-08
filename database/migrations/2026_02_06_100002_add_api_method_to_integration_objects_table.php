<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_objects', function (Blueprint $table) {
            $table->string('api_method', 50)->default('loadValueObjects')->after('sync_direction')
                ->comment('API method: loadValueObjects, findObjects, createObject, updateObject');
        });
    }

    public function down(): void
    {
        Schema::table('integration_objects', function (Blueprint $table) {
            $table->dropColumn('api_method');
        });
    }
};
