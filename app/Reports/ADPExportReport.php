<?php

namespace App\Reports;

use \koolreport\KoolReport;
use \koolreport\laravel\Friendship;
use \koolreport\processes\CalculatedColumn;
use \koolreport\processes\ColumnRename;
use \koolreport\processes\Filter;
use \koolreport\processes\Group;
use \koolreport\processes\Join;
use \koolreport\processes\Sort;
use \koolreport\core\DataStore;
use App\Helpers\KoolReportLaravelCompatibility;

class ADPExportReport extends KoolReport
{
    use Friendship;

    protected function settings()
    {
        return [
            'dataSources' => [
                'database' => [
                    'class' => '\koolreport\datasources\MySQLDataSource',
                    'host' => config('database.connections.mysql.host'),
                    'username' => config('database.connections.mysql.username'),
                    'password' => config('database.connections.mysql.password'),
                    'dbname' => config('database.connections.mysql.database'),
                    'port' => config('database.connections.mysql.port'),
                    'charset' => 'utf8mb4'
                ]
            ]
        ];
    }

    protected function setup()
    {
        // Ensure KoolReport Laravel 11 compatibility macros are loaded
        KoolReportLaravelCompatibility::initialize();

        // Get parameters for filtering
        $startDate = $this->params['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $this->params['end_date'] ?? now()->endOfMonth()->format('Y-m-d');
        $employeeIds = $this->params['employee_ids'] ?? [];
        $departmentIds = $this->params['department_ids'] ?? [];

        // Build SQL query with filters
        $sql = "
            SELECT
                a.id as attendance_id,
                e.external_id as employee_code,
                e.first_name,
                e.last_name,
                e.full_names as employee_name,
                d.name as department_name,
                d.external_department_id as department_code,
                a.punch_time,
                a.shift_date,
                pt.name as punch_type,
                c.name as classification,
                a.status,
                a.is_manual,
                e.pay_rate,
                e.overtime_rate,
                e.pay_type,
                e.full_time
            FROM attendances a
            LEFT JOIN employees e ON a.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN punch_types pt ON a.punch_type_id = pt.id
            LEFT JOIN classifications c ON a.classification_id = c.id
            WHERE a.punch_time >= ?
                AND a.punch_time <= ?
                AND a.status != 'deleted'
        ";

        $params = [
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        ];

        // Apply employee filter if specified
        if (!empty($employeeIds)) {
            $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
            $sql .= " AND e.id IN ($placeholders)";
            $params = array_merge($params, $employeeIds);
        }

        // Apply department filter if specified
        if (!empty($departmentIds)) {
            $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));
            $sql .= " AND d.id IN ($placeholders)";
            $params = array_merge($params, $departmentIds);
        }

        $sql .= " ORDER BY e.external_id ASC, a.shift_date ASC, a.punch_time ASC";

        // Build SQL with embedded values instead of prepared statements
        $escapedStartDate = addslashes($params[0]);
        $escapedEndDate = addslashes($params[1]);

        // Replace placeholders with actual values (replace one at a time)
        $finalSql = preg_replace('/\?/', "'$escapedStartDate'", $sql, 1);
        $finalSql = preg_replace('/\?/', "'$escapedEndDate'", $finalSql, 1);

        // Handle additional filters
        if (!empty($employeeIds)) {
            $escapedEmployeeIds = array_map('intval', $employeeIds);
            $placeholders = implode(',', $escapedEmployeeIds);
            $finalSql = str_replace(' AND e.id IN (' . implode(',', array_fill(0, count($employeeIds), '?')) . ')', " AND e.id IN ($placeholders)", $finalSql);
        }

        if (!empty($departmentIds)) {
            $escapedDepartmentIds = array_map('intval', $departmentIds);
            $placeholders = implode(',', $escapedDepartmentIds);
            $finalSql = str_replace(' AND d.id IN (' . implode(',', array_fill(0, count($departmentIds), '?')) . ')', " AND d.id IN ($placeholders)", $finalSql);
        }

        // \Log::info('Final SQL Query:', ['sql' => $finalSql]);
        $attendanceQuery = $this->src('database')->query($finalSql);

        // Process the data and create the ADP export format
        $attendanceQuery
            ->pipe(new CalculatedColumn([
                'punch_date' => function($row) {
                    return date('m/d/Y', strtotime($row['punch_time']));
                },
                'punch_time_formatted' => function($row) {
                    return date('H:i', strtotime($row['punch_time']));
                },
                'hours_worked' => function($row) {
                    // This will be calculated per day grouping
                    return 0;
                },
                'overtime_hours' => function($row) {
                    return 0;
                },
                'pay_code' => function($row) {
                    // Default pay code - can be customized
                    return $row['classification'] ?: 'REG';
                },
                'cost_center' => function($row) {
                    return $row['department_code'] ?: $row['department_name'];
                }
            ]))
            ->pipe(new ColumnRename([
                'employee_code' => 'Employee_ID',
                'employee_name' => 'Employee_Name',
                'punch_date' => 'Date',
                'punch_time_formatted' => 'Time',
                'hours_worked' => 'Hours',
                'overtime_hours' => 'OT_Hours',
                'pay_code' => 'Pay_Code',
                'cost_center' => 'Cost_Center',
                'department_name' => 'Department'
            ]))
            ->pipe($this->dataStore('attendance_export'));

        // Create a summary report for payroll processing
        $summarySql = "
            SELECT
                e.external_id as employee_id,
                e.first_name,
                e.last_name,
                e.full_names as employee_name,
                d.name as department_name,
                a.shift_date,
                e.pay_rate,
                e.overtime_rate,
                e.pay_type,
                COUNT(*) * 0.25 as total_hours
            FROM attendances a
            LEFT JOIN employees e ON a.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE a.punch_time >= ?
                AND a.punch_time <= ?
                AND a.status != 'deleted'
            GROUP BY e.external_id, e.first_name, e.last_name, e.full_names, d.name, a.shift_date, e.pay_rate, e.overtime_rate, e.pay_type
            ORDER BY e.external_id ASC, a.shift_date ASC
        ";

        // Build summary SQL with embedded values
        $finalSummarySql = preg_replace('/\?/', "'$escapedStartDate'", $summarySql, 1);
        $finalSummarySql = preg_replace('/\?/', "'$escapedEndDate'", $finalSummarySql, 1);

        // \Log::info('Final Summary SQL Query:', ['sql' => $finalSummarySql]);
        $this->src('database')->query($finalSummarySql)
            ->pipe(new CalculatedColumn([
                'regular_hours' => function($row) {
                    return min($row['total_hours'], 8); // Up to 8 hours regular
                },
                'overtime_hours' => function($row) {
                    return max(0, $row['total_hours'] - 8); // Over 8 hours is OT
                },
                'gross_pay' => function($row) {
                    $regular = min($row['total_hours'], 8) * ($row['pay_rate'] ?? 0);
                    $overtime = max(0, $row['total_hours'] - 8) * (($row['overtime_rate'] ?? $row['pay_rate']) ?? 0);
                    return $regular + $overtime;
                }
            ]))
            ->pipe($this->dataStore('payroll_summary'));
    }

    /**
     * Get the attendance export data formatted for ADP
     */
    public function getAttendanceExport()
    {
        return $this->dataStore('attendance_export');
    }

    /**
     * Get the payroll summary data
     */
    public function getPayrollSummary()
    {
        return $this->dataStore('payroll_summary');
    }

    /**
     * Export to CSV format for ADP import
     */
    public function exportToCSV($filename = null)
    {
        $filename = $filename ?: 'adp_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = storage_path('app/exports/' . $filename);

        // Ensure exports directory exists
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $data = $this->dataStore('attendance_export')->data();

        $file = fopen($filepath, 'w');

        // Write headers
        if (!empty($data)) {
            fputcsv($file, array_keys($data[0]), ',', '"', '\\');

            // Write data rows
            foreach ($data as $row) {
                fputcsv($file, $row, ',', '"', '\\');
            }
        }

        fclose($file);

        return $filepath;
    }

    /**
     * Export payroll summary to CSV
     */
    public function exportPayrollSummaryToCSV($filename = null)
    {
        $filename = $filename ?: 'payroll_summary_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = storage_path('app/exports/' . $filename);

        // Ensure exports directory exists
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $data = $this->dataStore('payroll_summary')->data();

        $file = fopen($filepath, 'w');

        // Write headers
        if (!empty($data)) {
            fputcsv($file, array_keys($data[0]), ',', '"', '\\');

            // Write data rows
            foreach ($data as $row) {
                fputcsv($file, $row, ',', '"', '\\');
            }
        }

        fclose($file);

        return $filepath;
    }
}