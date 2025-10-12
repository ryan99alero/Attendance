<?php

namespace App\Http\Controllers;

use App\Reports\ADPExportReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Carbon\Carbon;

class ReportsController extends Controller
{
    /**
     * Show the reports dashboard
     */
    public function dashboard()
    {
        // Basic dashboard with key metrics
        $metrics = [
            'total_employees' => \App\Models\Employee::where('is_active', true)->count(),
            'total_departments' => \App\Models\Department::count(),
            'todays_punches' => \App\Models\Attendance::whereDate('punch_time', today())->count(),
            'this_week_hours' => $this->calculateWeeklyHours(),
        ];

        // Detect theme from Filament or browser preference
        $isDark = $this->detectDarkMode();

        return view('reports.dashboard', compact('metrics', 'isDark'));
    }

    /**
     * Detect if dark mode should be used
     */
    private function detectDarkMode()
    {
        // Check if user has theme preference in session/cookie from Filament
        if (session()->has('filament_theme')) {
            $theme = session('filament_theme');
            if ($theme === 'dark') return true;
            if ($theme === 'light') return false;
        }

        // Check for theme cookie that Filament might set
        if (request()->cookie('theme')) {
            $theme = request()->cookie('theme');
            if ($theme === 'dark') return true;
            if ($theme === 'light') return false;
        }

        // Default to system preference detection via JavaScript
        // For now, we'll default to light mode and let JS handle it
        return false;
    }

    /**
     * Show sample report data
     */
    public function sample()
    {
        // Generate a sample report with dummy data
        $sampleData = [
            [
                'Employee_ID' => 'EMP001',
                'Employee_Name' => 'John Doe',
                'Date' => '03/15/2024',
                'Time' => '08:00',
                'Hours' => '8.00',
                'OT_Hours' => '0.00',
                'Pay_Code' => 'REG',
                'Cost_Center' => 'ADMIN',
                'Department' => 'Administration'
            ],
            [
                'Employee_ID' => 'EMP002',
                'Employee_Name' => 'Jane Smith',
                'Date' => '03/15/2024',
                'Time' => '09:00',
                'Hours' => '8.50',
                'OT_Hours' => '0.50',
                'Pay_Code' => 'REG',
                'Cost_Center' => 'PROD',
                'Department' => 'Production'
            ],
        ];

        return view('reports.sample', compact('sampleData'));
    }

    /**
     * Generate and download ADP export
     */
    public function generateADPExport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'employee_ids' => 'nullable|array',
            'department_ids' => 'nullable|array',
            'format' => 'sometimes|in:csv,excel,json'
        ]);

        try {
            $report = new ADPExportReport();

            $report->run([
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'employee_ids' => $request->employee_ids ?? [],
                'department_ids' => $request->department_ids ?? []
            ]);

            $startDate = Carbon::parse($request->start_date)->format('Y-m-d');
            $endDate = Carbon::parse($request->end_date)->format('Y-m-d');

            switch ($request->get('format', 'csv')) {
                case 'json':
                    return $this->exportToJson($report, $startDate, $endDate);
                case 'excel':
                    return $this->exportToExcel($report, $startDate, $endDate);
                default:
                    return $this->exportToCSV($report, $startDate, $endDate);
            }

        } catch (\Exception $e) {
            \Log::error('ADP Export Error: ' . $e->getMessage());
            return response()->json(['error' => 'Export generation failed'], 500);
        }
    }

    /**
     * Export to CSV
     */
    private function exportToCSV($report, $startDate, $endDate)
    {
        $filename = "adp_export_{$startDate}_to_{$endDate}.csv";
        $filepath = $report->exportToCSV($filename);

        return response()->download($filepath, $filename, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend();
    }

    /**
     * Export to JSON
     */
    private function exportToJson($report, $startDate, $endDate)
    {
        $data = $report->dataStore('attendance_export')->data();
        $filename = "adp_export_{$startDate}_to_{$endDate}.json";

        return response()->json($data)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Export to Excel (placeholder - would need Excel package)
     */
    private function exportToExcel($report, $startDate, $endDate)
    {
        // For now, fallback to CSV
        // In a real implementation, you'd use maatwebsite/excel or similar
        return $this->exportToCSV($report, $startDate, $endDate);
    }

    /**
     * Calculate weekly hours for dashboard
     */
    private function calculateWeeklyHours()
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        // This is a simplified calculation
        // In practice, you'd need more complex logic to calculate actual hours
        $punchCount = \App\Models\Attendance::whereBetween('punch_time', [$startOfWeek, $endOfWeek])
            ->where('status', '!=', 'deleted')
            ->count();

        // Rough estimate: divide by 2 (in/out pairs) and multiply by 8 hours average
        return round(($punchCount / 2) * 8, 2);
    }

    /**
     * Get report configuration for GUI builder
     */
    public function getConfiguration()
    {
        return response()->json([
            'available_fields' => [
                'employee_id' => 'Employee ID',
                'employee_name' => 'Employee Name',
                'punch_date' => 'Punch Date',
                'punch_time' => 'Punch Time',
                'hours_worked' => 'Hours Worked',
                'overtime_hours' => 'Overtime Hours',
                'pay_code' => 'Pay Code',
                'cost_center' => 'Cost Center',
                'department' => 'Department',
                'pay_rate' => 'Pay Rate',
                'gross_pay' => 'Gross Pay'
            ],
            'available_filters' => [
                'date_range' => 'Date Range',
                'employee_ids' => 'Specific Employees',
                'department_ids' => 'Specific Departments',
                'pay_codes' => 'Pay Codes',
                'status' => 'Record Status'
            ],
            'export_formats' => [
                'csv' => 'CSV',
                'excel' => 'Excel',
                'json' => 'JSON',
                'xml' => 'XML'
            ]
        ]);
    }
}