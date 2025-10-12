<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Daily Attendance Trends';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        // Get the last 30 days of attendance data
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $attendanceData = Attendance::select(
            DB::raw('DATE(punch_time) as date'),
            DB::raw('COUNT(*) as punch_count')
        )
        ->where('punch_time', '>=', $thirtyDaysAgo)
        ->where('status', '!=', 'deleted')
        ->groupBy(DB::raw('DATE(punch_time)'))
        ->orderBy('date')
        ->get();

        // Create arrays for labels and data
        $labels = [];
        $data = [];

        // Fill in missing dates with zero values
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dateString = $date->format('Y-m-d');
            $labels[] = $date->format('M j');

            $dayData = $attendanceData->firstWhere('date', $dateString);
            $data[] = $dayData ? $dayData->punch_count : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Daily Punches',
                    'data' => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'fill' => true,
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
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Punches'
                    ]
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Date'
                    ]
                ]
            ],
        ];
    }
}