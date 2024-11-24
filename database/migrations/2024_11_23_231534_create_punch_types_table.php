<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('punch_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Name of the punch type (e.g., Clock In, Clock Out)');
            $table->text('description')->nullable()->comment('Description of the punch type');
            $table->boolean('is_active')->default(true)->comment('Indicates if the punch type is active');
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
        Schema::dropIfExists('punch_types');
    }
};
