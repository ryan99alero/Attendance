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
        // 1. Add vacation configuration fields to existing company_setup table
        Schema::table('company_setup', function (Blueprint $table) {
            $table->enum('vacation_accrual_method', ['calendar_year', 'pay_period', 'anniversary'])
                ->default('anniversary')
                ->comment('Primary vacation accrual method');
            $table->json('vacation_config')->nullable()->comment('Method-specific configuration JSON');
            $table->boolean('allow_carryover')->default(true)->comment('Allow vacation carryover to next period');
            $table->decimal('max_carryover_hours', 8, 2)->nullable()->comment('Maximum hours that can carry over');
            $table->decimal('max_accrual_balance', 8, 2)->nullable()->comment('Maximum vacation balance cap');
            $table->boolean('prorate_new_hires')->default(true)->comment('Prorate vacation for mid-period hires');
        });

        // 2. Vacation policies - define tenure-based vacation entitlements
        Schema::create('vacation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_name')->comment('Name of this policy (e.g., "Standard Employee")');
            $table->integer('min_tenure_years')->default(0)->comment('Minimum years for this tier');
            $table->integer('max_tenure_years')->nullable()->comment('Maximum years for this tier (null = no max)');
            $table->decimal('vacation_days_per_year', 5, 2)->comment('Vacation days earned per year');
            $table->decimal('vacation_hours_per_year', 8, 2)->comment('Vacation hours earned per year');
            $table->boolean('is_active')->default(true)->comment('Whether this policy tier is active');
            $table->integer('sort_order')->default(0)->comment('Sort order for policy display');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['min_tenure_years']);
        });

        // 3. Vacation transactions - all accrual and usage events
        Schema::create('vacation_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->comment('Foreign key to employees');
            $table->enum('transaction_type', ['accrual', 'usage', 'adjustment', 'carryover', 'expiration'])
                ->comment('Type of vacation transaction');
            $table->decimal('hours', 8, 2)->comment('Hours affected (positive for accrual, negative for usage)');
            $table->date('transaction_date')->comment('Date transaction occurred');
            $table->date('effective_date')->comment('Date transaction takes effect');
            $table->string('accrual_period')->nullable()->comment('Accrual period reference (e.g., "2024-Q1", "2024-Anniversary")');
            $table->text('description')->nullable()->comment('Human-readable description');
            $table->json('metadata')->nullable()->comment('Additional transaction data (pay period, policy used, etc.)');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index(['employee_id', 'transaction_date']);
            $table->index(['employee_id', 'transaction_type']);
        });

        // 4. Employee vacation assignments - link employees to vacation policies
        Schema::create('employee_vacation_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->comment('Foreign key to employees');
            $table->unsignedBigInteger('vacation_policy_id')->comment('Foreign key to vacation_policies');
            $table->date('effective_date')->comment('Date this policy assignment became effective');
            $table->date('end_date')->nullable()->comment('Date this assignment ends (null = current)');
            $table->json('override_settings')->nullable()->comment('Employee-specific overrides to policy');
            $table->boolean('is_active')->default(true)->comment('Whether this assignment is active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('vacation_policy_id')->references('id')->on('vacation_policies')->onDelete('cascade');
            $table->index(['employee_id', 'effective_date']);
        });

        // 5. Update existing company_setup record with vacation configuration
        DB::table('company_setup')->updateOrInsert(
            ['id' => 1], // Assuming single company setup
            [
                'vacation_accrual_method' => 'anniversary',
                'vacation_config' => json_encode([
                    'anniversary_method' => [
                        'first_year_waiting_period' => true,
                        'award_on_anniversary' => true,
                        'max_days_cap' => 15
                    ]
                ]),
                'allow_carryover' => true,
                'max_carryover_hours' => 40,
                'max_accrual_balance' => 120,
                'prorate_new_hires' => false,
                'updated_at' => now(),
            ]
        );

        // 6. Insert Rand Graphics vacation policy tiers
        $policies = [
            ['min_tenure' => 1, 'max_tenure' => 5, 'days' => 10, 'name' => 'Years 1-5'],
            ['min_tenure' => 6, 'max_tenure' => 6, 'days' => 11, 'name' => 'Year 6'],
            ['min_tenure' => 7, 'max_tenure' => 7, 'days' => 12, 'name' => 'Year 7'],
            ['min_tenure' => 8, 'max_tenure' => 8, 'days' => 13, 'name' => 'Year 8'],
            ['min_tenure' => 9, 'max_tenure' => 9, 'days' => 14, 'name' => 'Year 9'],
            ['min_tenure' => 10, 'max_tenure' => null, 'days' => 15, 'name' => 'Year 10+'],
        ];

        foreach ($policies as $index => $policy) {
            DB::table('vacation_policies')->insert([
                'policy_name' => $policy['name'],
                'min_tenure_years' => $policy['min_tenure'],
                'max_tenure_years' => $policy['max_tenure'],
                'vacation_days_per_year' => $policy['days'],
                'vacation_hours_per_year' => $policy['days'] * 8,
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_vacation_assignments');
        Schema::dropIfExists('vacation_transactions');
        Schema::dropIfExists('vacation_policies');

        // Remove vacation fields from company_setup table
        Schema::table('company_setup', function (Blueprint $table) {
            $table->dropColumn([
                'vacation_accrual_method',
                'vacation_config',
                'allow_carryover',
                'max_carryover_hours',
                'max_accrual_balance',
                'prorate_new_hires'
            ]);
        });
    }
};
