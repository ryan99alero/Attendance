<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_objects', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->unsignedBigInteger('connection_id')->comment('Foreign key to integration_connections');
            $table->string('object_name', 100)->comment('API object name: Job, Customer, Employee, etc.');
            $table->string('display_name', 100)->comment('Human-friendly display name');
            $table->text('description')->nullable()->comment('Description of this object type');

            // Primary key info
            $table->string('primary_key_field', 100)->default('@id')->comment('XPath to primary key field');
            $table->string('primary_key_type', 50)->default('Integer')->comment('Data type of primary key');

            // Available fields (cached from API discovery)
            $table->json('available_fields')->nullable()->comment('JSON array of available fields from API');
            $table->json('available_children')->nullable()->comment('JSON array of child objects');

            // Local mapping
            $table->string('local_model', 100)->nullable()->comment('Laravel model class if mapped: App\\Models\\Employee');
            $table->string('local_table', 100)->nullable()->comment('Local database table if mapped');

            // Sync settings
            $table->boolean('sync_enabled')->default(false)->comment('Whether sync is enabled for this object');
            $table->string('sync_direction', 20)->default('pull')->comment('pull, push, or bidirectional');
            $table->string('sync_frequency', 50)->nullable()->comment('Sync schedule: hourly, daily, manual');
            $table->timestamp('last_synced_at')->nullable()->comment('Last successful sync');

            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('integration_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'object_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_objects');
    }
};
