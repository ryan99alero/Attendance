<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('rounding_rules', function (Blueprint $table) {
            $table->id()->comment('Primary key of the rounding_rules table');
            $table->unsignedBigInteger('round_group_id')->nullable()->comment('Foreign key referencing the round_groups table');
            $table->integer('minute_min')->comment('Minimum minute value for the rounding range');
            $table->integer('minute_max')->comment('Maximum minute value for the rounding range');
            $table->integer('new_minute')->comment('New minute value after rounding');
            $table->decimal('new_minute_decimal', 5, 2)->comment('Decimal equivalent of the rounded minute value');
            $table->timestamps();

            // Indexes
            $table->index(['minute_min', 'minute_max'], 'idx_rounding_range')->comment('Index for optimizing queries on minute range');
            $table->index('round_group_id', 'idx_rounding_group')->comment('Index for optimizing queries by round group');

            // Foreign key constraint
            $table->foreign('round_group_id')
                ->references('id')->on('round_groups')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });

        // Add triggers for validations
        DB::unprepared("
            CREATE TRIGGER before_insert_rounding_rules
            BEFORE INSERT ON rounding_rules
            FOR EACH ROW
            BEGIN
                IF NEW.minute_min > NEW.minute_max THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'minute_min cannot be greater than minute_max';
                END IF;
            END;
        ");
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::unprepared("DROP TRIGGER IF EXISTS before_insert_rounding_rules;");

        // Drop the table
        Schema::dropIfExists('rounding_rules');
    }
};
