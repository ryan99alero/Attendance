<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pay_period_employee_summaries', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('pay_period_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('classification_id');

            $table->decimal('hours', 8, 2)->default(0.00)
                ->comment('Total hours for this classification in this pay period');

            $table->boolean('is_finalized')->default(false)
                ->comment('Locked after pay period is posted');

            $table->text('notes')->nullable()
                ->comment('Notes for manual adjustments');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('pay_period_id')
                ->references('id')
                ->on('pay_periods')
                ->onDelete('cascade');

            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->onDelete('cascade');

            $table->foreign('classification_id')
                ->references('id')
                ->on('classifications')
                ->onDelete('restrict');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Unique constraint: one summary per employee per classification per pay period
            $table->unique(['pay_period_id', 'employee_id', 'classification_id'], 'unique_summary');

            // Indexes for common queries
            $table->index(['pay_period_id', 'employee_id'], 'pp_emp_summary_period_employee');
            $table->index(['employee_id', 'classification_id'], 'pp_emp_summary_employee_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_period_employee_summaries');
    }
};
