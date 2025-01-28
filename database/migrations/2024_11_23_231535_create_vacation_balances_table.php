<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::disableForeignKeyConstraints();
        Schema::create('vacation_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->comment('Foreign key to Employees');
            $table->decimal('accrual_rate', 8, 2)->default(0.00)->comment('Rate at which vacation time accrues per pay period');
            $table->decimal('accrued_hours', 8, 2)->default(0.00)->comment('Total vacation hours accrued');
            $table->decimal('used_hours', 8, 2)->default(0.00)->comment('Total vacation hours used');
            $table->decimal('carry_over_hours', 8, 2)->default(0.00)->comment('Vacation hours carried over from the previous year');
            $table->decimal('cap_hours', 8, 2)->default(0.00)->comment('Maximum allowed vacation hours (cap)');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_balances');
    }
};
