<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('address2', 255)->nullable()->after('address')->comment('Second address line (apt, suite, etc.)');
            $table->date('birth_date')->nullable()->after('phone')->comment('Date of birth');
            $table->string('emergency_contact', 100)->nullable()->after('birth_date')->comment('Emergency contact name');
            $table->string('emergency_phone', 20)->nullable()->after('emergency_contact')->comment('Emergency contact phone number');
            $table->text('notes')->nullable()->after('emergency_phone')->comment('General employee notes');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'address2',
                'birth_date',
                'emergency_contact',
                'emergency_phone',
                'notes',
            ]);
        });
    }
};
