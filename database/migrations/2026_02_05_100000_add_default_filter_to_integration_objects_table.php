<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_objects', function (Blueprint $table) {
            $table->text('default_filter')->nullable()->after('available_children')
                ->comment('Default XPath filter for syncs, e.g. @status = \'A\'');
        });
    }

    public function down(): void
    {
        Schema::table('integration_objects', function (Blueprint $table) {
            $table->dropColumn('default_filter');
        });
    }
};
