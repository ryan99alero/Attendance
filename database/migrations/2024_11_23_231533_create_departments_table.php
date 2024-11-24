<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Department name');
            $table->bigInteger('manager_id')->nullable()->comment('Foreign key to Employees for department manager');
            $table->bigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->bigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};