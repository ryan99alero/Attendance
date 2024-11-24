<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Name of the holiday');
            $table->date('date')->comment('Date of the holiday');
            $table->boolean('is_recurring')->default(false)->comment('Indicates if the holiday recurs annually');
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
        Schema::dropIfExists('holidays');
    }
};
