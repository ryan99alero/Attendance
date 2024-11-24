<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('pay_periods', function (Blueprint $table) {
            $table->id();
            $table->date('start_date')->comment('Start date of the pay period');
            $table->date('end_date')->comment('End date of the pay period');
            $table->boolean('is_processed')->default(false)->comment('Indicates if the pay period has been processed');
            $table->unsignedBigInteger('processed_by')->nullable()->comment('Foreign key to Users for processor');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Foreign key to Users for record creator');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Foreign key to Users for last updater');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_periods');
    }
};
