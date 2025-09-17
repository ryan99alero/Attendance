<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Employee Vacation Summary View - Current vacation status for all employees
        DB::statement("
            CREATE VIEW employee_vacation_summary AS
            SELECT
                e.id as employee_id,
                e.first_name,
                e.last_name,
                e.date_of_hire,
                CASE
                    WHEN e.date_of_hire IS NULL THEN 0
                    ELSE FLOOR(DATEDIFF(CURDATE(), e.date_of_hire) / 365.25)
                END as years_of_service,

                -- Current policy assignment
                eva.vacation_policy_id,
                vp.policy_name,
                vp.vacation_days_per_year,
                vp.vacation_hours_per_year,

                -- Transaction totals
                COALESCE(accruals.total_accrued, 0) as total_accrued,
                COALESCE(used_hours.total_used, 0) as total_used,
                COALESCE(adjustments.total_adjustments, 0) as total_adjustments,

                -- Current balance
                COALESCE(accruals.total_accrued, 0) - COALESCE(used_hours.total_used, 0) + COALESCE(adjustments.total_adjustments, 0) as current_balance,

                -- Company accrual method
                cs.vacation_accrual_method,
                cs.max_accrual_balance,
                cs.max_carryover_hours,

                -- Last transaction date
                last_trans.last_transaction_date

            FROM employees e
            LEFT JOIN employee_vacation_assignments eva ON (
                eva.employee_id = e.id
                AND eva.is_active = 1
                AND eva.effective_date <= CURDATE()
                AND (eva.end_date IS NULL OR eva.end_date >= CURDATE())
            )
            LEFT JOIN vacation_policies vp ON vp.id = eva.vacation_policy_id
            LEFT JOIN company_setup cs ON cs.id = 1

            -- Accrual totals
            LEFT JOIN (
                SELECT
                    employee_id,
                    SUM(hours) as total_accrued
                FROM vacation_transactions
                WHERE transaction_type = 'accrual'
                GROUP BY employee_id
            ) accruals ON accruals.employee_id = e.id

            -- Usage totals (make positive)
            LEFT JOIN (
                SELECT
                    employee_id,
                    ABS(SUM(hours)) as total_used
                FROM vacation_transactions
                WHERE transaction_type = 'usage'
                GROUP BY employee_id
            ) used_hours ON used_hours.employee_id = e.id

            -- Adjustment totals
            LEFT JOIN (
                SELECT
                    employee_id,
                    SUM(hours) as total_adjustments
                FROM vacation_transactions
                WHERE transaction_type = 'adjustment'
                GROUP BY employee_id
            ) adjustments ON adjustments.employee_id = e.id

            -- Last transaction
            LEFT JOIN (
                SELECT
                    employee_id,
                    MAX(transaction_date) as last_transaction_date
                FROM vacation_transactions
                GROUP BY employee_id
            ) last_trans ON last_trans.employee_id = e.id

            WHERE e.is_active = 1
        ");

        // Anniversary Vacation Status View - Specific to anniversary-based accrual
        DB::statement("
            CREATE VIEW anniversary_vacation_status AS
            SELECT
                e.id as employee_id,
                e.first_name,
                e.last_name,
                e.date_of_hire,

                -- Anniversary calculations
                CASE
                    WHEN e.date_of_hire IS NULL THEN NULL
                    ELSE DATE_ADD(e.date_of_hire, INTERVAL FLOOR(DATEDIFF(CURDATE(), e.date_of_hire) / 365.25) YEAR)
                END as last_anniversary_date,

                CASE
                    WHEN e.date_of_hire IS NULL THEN NULL
                    ELSE DATE_ADD(e.date_of_hire, INTERVAL (FLOOR(DATEDIFF(CURDATE(), e.date_of_hire) / 365.25) + 1) YEAR)
                END as next_anniversary_date,

                FLOOR(DATEDIFF(CURDATE(), e.date_of_hire) / 365.25) as completed_years,

                -- Current year transactions (anniversary to anniversary)
                COALESCE(current_accruals.current_year_accrued, 0) as current_year_accrued,
                COALESCE(current_used.current_year_used, 0) as current_year_used,

                -- Policy information
                vp.policy_name,
                vp.vacation_hours_per_year as annual_entitlement,

                -- Check if due for anniversary accrual
                CASE
                    WHEN e.date_of_hire IS NULL THEN 0
                    WHEN CURDATE() >= DATE_ADD(e.date_of_hire, INTERVAL (FLOOR(DATEDIFF(CURDATE(), e.date_of_hire) / 365.25) + 1) YEAR) THEN 1
                    ELSE 0
                END as is_due_for_accrual

            FROM employees e
            LEFT JOIN employee_vacation_assignments eva ON (
                eva.employee_id = e.id
                AND eva.is_active = 1
                AND eva.effective_date <= CURDATE()
                AND (eva.end_date IS NULL OR eva.end_date >= CURDATE())
            )
            LEFT JOIN vacation_policies vp ON vp.id = eva.vacation_policy_id

            -- Current anniversary year accruals
            LEFT JOIN (
                SELECT
                    vt.employee_id,
                    SUM(vt.hours) as current_year_accrued
                FROM vacation_transactions vt
                JOIN employees e2 ON e2.id = vt.employee_id
                WHERE vt.transaction_type = 'accrual'
                AND vt.transaction_date >= DATE_ADD(e2.date_of_hire, INTERVAL FLOOR(DATEDIFF(CURDATE(), e2.date_of_hire) / 365.25) YEAR)
                AND vt.transaction_date < DATE_ADD(e2.date_of_hire, INTERVAL (FLOOR(DATEDIFF(CURDATE(), e2.date_of_hire) / 365.25) + 1) YEAR)
                GROUP BY vt.employee_id
            ) current_accruals ON current_accruals.employee_id = e.id

            -- Current anniversary year usage
            LEFT JOIN (
                SELECT
                    vt.employee_id,
                    ABS(SUM(vt.hours)) as current_year_used
                FROM vacation_transactions vt
                JOIN employees e2 ON e2.id = vt.employee_id
                WHERE vt.transaction_type = 'usage'
                AND vt.transaction_date >= DATE_ADD(e2.date_of_hire, INTERVAL FLOOR(DATEDIFF(CURDATE(), e2.date_of_hire) / 365.25) YEAR)
                AND vt.transaction_date < DATE_ADD(e2.date_of_hire, INTERVAL (FLOOR(DATEDIFF(CURDATE(), e2.date_of_hire) / 365.25) + 1) YEAR)
                GROUP BY vt.employee_id
            ) current_used ON current_used.employee_id = e.id

            WHERE e.is_active = 1
            AND e.date_of_hire IS NOT NULL
        ");

        // Vacation Accrual History View - Track accrual patterns over time
        DB::statement("
            CREATE VIEW vacation_accrual_history AS
            SELECT
                vt.id,
                vt.employee_id,
                e.first_name,
                e.last_name,
                vt.transaction_type,
                vt.hours,
                vt.transaction_date,
                vt.effective_date,
                vt.accrual_period,
                vt.description,

                -- Running balance calculation
                (
                    SELECT
                        COALESCE(SUM(
                            CASE
                                WHEN vt2.transaction_type = 'accrual' THEN vt2.hours
                                WHEN vt2.transaction_type = 'usage' THEN vt2.hours  -- hours already negative
                                WHEN vt2.transaction_type = 'adjustment' THEN vt2.hours
                                ELSE 0
                            END
                        ), 0)
                    FROM vacation_transactions vt2
                    WHERE vt2.employee_id = vt.employee_id
                    AND vt2.transaction_date <= vt.transaction_date
                    AND vt2.id <= vt.id
                ) as running_balance,

                -- Policy at time of transaction
                vp.policy_name,
                vp.vacation_hours_per_year

            FROM vacation_transactions vt
            JOIN employees e ON e.id = vt.employee_id
            LEFT JOIN employee_vacation_assignments eva ON (
                eva.employee_id = vt.employee_id
                AND eva.effective_date <= vt.transaction_date
                AND (eva.end_date IS NULL OR eva.end_date >= vt.transaction_date)
            )
            LEFT JOIN vacation_policies vp ON vp.id = eva.vacation_policy_id

            ORDER BY vt.employee_id, vt.transaction_date, vt.id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vacation_accrual_history');
        DB::statement('DROP VIEW IF EXISTS anniversary_vacation_status');
        DB::statement('DROP VIEW IF EXISTS employee_vacation_summary');
    }
};
