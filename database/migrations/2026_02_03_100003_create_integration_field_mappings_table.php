<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_field_mappings', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->unsignedBigInteger('object_id')->comment('Foreign key to integration_objects');

            // External field info
            $table->string('external_field', 100)->comment('External field name from API');
            $table->string('external_xpath', 255)->comment('XPath to field in API response');
            $table->string('external_type', 50)->comment('Data type from API: String, Integer, Date, etc.');

            // Local field info
            $table->string('local_field', 100)->comment('Local database column name');
            $table->string('local_type', 50)->comment('Local data type: string, integer, datetime, etc.');

            // Transformation
            $table->string('transform', 50)->nullable()->comment('Transformation: date_ms_to_carbon, cents_to_dollars, etc.');
            $table->json('transform_options')->nullable()->comment('Options for transformation');

            // Sync settings
            $table->boolean('sync_on_pull')->default(true)->comment('Update local field when pulling from API');
            $table->boolean('sync_on_push')->default(false)->comment('Update API field when pushing');
            $table->boolean('is_identifier')->default(false)->comment('Used to match records (like external_id)');

            $table->timestamps();

            $table->foreign('object_id')->references('id')->on('integration_objects')->onDelete('cascade');
            $table->unique(['object_id', 'external_field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_field_mappings');
    }
};
