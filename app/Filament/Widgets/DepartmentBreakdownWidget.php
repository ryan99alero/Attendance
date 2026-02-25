<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Department;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DepartmentBreakdownWidget extends ChartWidget
{
    protected ?string $heading = 'Attendance by Department';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $data = $this->getDepartmentAttendanceData();

        return [
            'datasets' => [
                [
                    'label' => 'Punches Today',
                    'data' => $data['values'],
                    'backgroundColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)',
                        'rgb(239, 68, 68)',
                        'rgb(139, 92, 246)',
                        'rgb(236, 72, 153)',
                        'rgb(6, 182, 212)',
                        'rgb(34, 197, 94)',
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * Get attendance data by department for today
     */
    private function getDepartmentAttendanceData(): array
    {
        $departmentData = DB::table('attendances')
            ->join('employees', 'attendances.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->whereDate('attendances.punch_time', Carbon::today())
            ->where('attendances.status', '!=', 'deleted')
            ->select('departments.name as department_name', DB::raw('COUNT(*) as punch_count'))
            ->groupBy('departments.id', 'departments.name')
            ->orderBy('punch_count', 'desc')
            ->get();

        // Handle case where there's no data
        if ($departmentData->isEmpty()) {
            return [
                'labels' => ['No Data'],
                'values' => [1],
            ];
        }

        return [
            'labels' => $departmentData->pluck('department_name')->toArray(),
            'values' => $departmentData->pluck('punch_count')->toArray(),
        ];
    }

    /**
     * Widget options
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { return context.label + ": " + context.parsed + " punches"; }',
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }

    /**
     * Refresh interval in seconds
     */
    protected ?string $pollingInterval = '120s';
}
