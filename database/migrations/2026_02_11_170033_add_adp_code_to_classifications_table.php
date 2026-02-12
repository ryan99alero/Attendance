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
        Schema::table('classifications', function (Blueprint $table) {
            // ADP hour code (H=Holiday, V=Vacation, S=Sick, D=Double Time, P=Personal, etc.)
            $table->string('adp_code', 2)->nullable()->after('code');

            // Whether this classification maps to the standard Reg Hours column
            $table->boolean('is_regular')->default(false)->after('adp_code');

            // Whether this classification maps to the standard O/T Hours column
            $table->boolean('is_overtime')->default(false)->after('is_regular');
        });

        // Update existing classifications with ADP codes
        DB::table('classifications')->where('code', 'HOLIDAY')->update(['adp_code' => 'H']);
        DB::table('classifications')->where('code', 'VACATION')->update(['adp_code' => 'V']);
        DB::table('classifications')->where('code', 'SICK')->update(['adp_code' => 'S']);
        DB::table('classifications')->where('code', 'TRAINING')->update(['adp_code' => 'P']); // Personal
        DB::table('classifications')->where('code', 'REGULAR')->update(['is_regular' => true]);

        // Add missing classifications for overtime
        DB::table('classifications')->insertOrIgnore([
            ['code' => 'OVERTIME', 'name' => 'Overtime', 'adp_code' => null, 'is_regular' => false, 'is_overtime' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'DOUBLE_TIME', 'name' => 'Double Time', 'adp_code' => 'D', 'is_regular' => false, 'is_overtime' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classifications', function (Blueprint $table) {
            $table->dropColumn(['adp_code', 'is_regular', 'is_overtime']);
        });

        DB::table('classifications')->whereIn('code', ['OVERTIME', 'DOUBLE_TIME'])->delete();
    }
};
