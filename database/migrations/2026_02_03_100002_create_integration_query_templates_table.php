<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_query_templates', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->unsignedBigInteger('connection_id')->comment('Foreign key to integration_connections');
            $table->unsignedBigInteger('object_id')->nullable()->comment('Foreign key to integration_objects');

            $table->string('name', 100)->comment('Template name: Get Jobs with Parts, Employee List, etc.');
            $table->text('description')->nullable()->comment('Description of what this query does');

            // The loadValueObjects request structure
            $table->string('object_name', 100)->comment('Root object to query');
            $table->json('fields')->comment('Array of field definitions with name and xpath');
            $table->json('children')->nullable()->comment('Array of child object queries');
            $table->json('filter')->nullable()->comment('Filter/where conditions');
            $table->json('sort')->nullable()->comment('Sort order');

            // Pagination defaults
            $table->integer('default_limit')->default(100)->comment('Default records per page');
            $table->integer('max_limit')->default(1000)->comment('Maximum records per request');

            // Usage tracking
            $table->integer('usage_count')->default(0)->comment('Times this template has been used');
            $table->timestamp('last_used_at')->nullable()->comment('Last time this template was used');

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('integration_connections')->onDelete('cascade');
            $table->foreign('object_id')->references('id')->on('integration_objects')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_query_templates');
    }
};
