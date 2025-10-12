<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Reports\ADPExportReport;
use Carbon\Carbon;

class KoolReportWidget extends Widget
{
    protected static string $view = 'filament.widgets.kool-report-widget';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function getPayrollSummaryData()
    {
        try {
            $report = new ADPExportReport();

            $report->run([
                'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d'),
                'end_date' => Carbon::now()->endOfWeek()->format('Y-m-d'),
                'employee_ids' => [],
                'department_ids' => []
            ]);

            $summaryData = $report->getPayrollSummary()->data();

            // Return only first 10 records for widget display
            return array_slice($summaryData, 0, 10);

        } catch (\Exception $e) {
            \Log::error('KoolReport Widget Error: ' . $e->getMessage());
            return [];
        }
    }

    public function getWeeklyTotals()
    {
        try {
            $summaryData = $this->getPayrollSummaryData();

            $totalHours = array_sum(array_column($summaryData, 'total_hours'));
            $totalGrossPay = array_sum(array_column($summaryData, 'gross_pay'));
            $avgHoursPerEmployee = count($summaryData) > 0 ? round($totalHours / count($summaryData), 1) : 0;

            return [
                'total_hours' => round($totalHours, 1),
                'total_gross_pay' => number_format($totalGrossPay, 2),
                'avg_hours_per_employee' => $avgHoursPerEmployee,
                'employee_count' => count($summaryData)
            ];

        } catch (\Exception $e) {
            \Log::error('Weekly Totals Error: ' . $e->getMessage());
            return [
                'total_hours' => 0,
                'total_gross_pay' => '0.00',
                'avg_hours_per_employee' => 0,
                'employee_count' => 0
            ];
        }
    }
}