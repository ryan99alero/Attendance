<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('overtime_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name', 100)->comment('Name of the overtime rule');
            $table->integer('hours_threshold')->default(40)->comment('Hours threshold for overtime calculation');
            $table->decimal('multiplier', 5, 2)->default(1.5)->comment('Overtime pay multiplier');
            $table->bigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->bigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_rules');
    }
};
