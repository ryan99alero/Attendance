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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->unique()->after('id')->comment('Foreign key to Employee, links the user account to an employee');
            $table->timestamp('last_login')->nullable()->after('password')->comment('Timestamp of the last login');
            $table->tinyInteger('is_manager')->default(0)->after('last_login')->comment('Flag indicating if the user is a manager');
            $table->tinyInteger('is_admin')->default(0)->after('is_manager')->comment('Flag indicating if the user has admin privileges');
            $table->unsignedBigInteger('created_by')->nullable()->after('updated_at')->comment('Foreign key to Users, indicating the record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by')->comment('Foreign key to Users, indicating the last updater');

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_id', 'last_login', 'is_manager', 'is_admin', 'created_by', 'updated_by']);
        });
    }
};
