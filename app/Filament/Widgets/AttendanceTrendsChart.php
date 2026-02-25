<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class AttendanceTrendsChart extends ChartWidget
{
    protected ?string $heading = 'Attendance Trends';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $days = 14; // Last 14 days
        $labels = [];
        $presentData = [];
        $absentData = [];

        $totalEmployees = Employee::where('is_active', true)->count();

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('M j');

            // Count unique employees who clocked in
            $present = Attendance::whereDate('shift_date', $date)
                ->where('punch_state', 'start')
                ->distinct('employee_id')
                ->count('employee_id');

            $presentData[] = $present;
            $absentData[] = max(0, $totalEmployees - $present);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Present',
                    'data' => $presentData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Absent',
                    'data' => $absentData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }
}
