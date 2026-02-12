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
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('payroll_provider_id')->nullable()->after('department_id')
                ->comment('FK to integration_connections for payroll export destination');

            $table->foreign('payroll_provider_id')
                ->references('id')
                ->on('integration_connections')
                ->onDelete('set null');

            $table->index('payroll_provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['payroll_provider_id']);
            $table->dropIndex(['payroll_provider_id']);
            $table->dropColumn('payroll_provider_id');
        });
    }
};
