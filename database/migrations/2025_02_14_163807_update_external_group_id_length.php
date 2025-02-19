<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->string('external_group_id', 40)->change();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('external_group_id', 40)->nullable()->change();
        });

        Schema::table('attendance_time_groups', function (Blueprint $table) {
            $table->string('external_group_id', 40)->change();
        });
    }

    public function down()
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->string('external_group_id', 20)->change();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('external_group_id', 20)->change();
        });

        Schema::table('attendance_time_groups', function (Blueprint $table) {
            $table->string('external_group_id', 20)->change();
        });
    }
};
