<?php

namespace App\Filament\Employee\Widgets;

use App\Models\Attendance;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class EmployeeStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $employee = Auth::user()->employee;

        if (! $employee) {
            return [
                Stat::make('Error', 'No employee record found')
                    ->color('danger'),
            ];
        }

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        // Get today's attendance
        $todayAttendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('shift_date', $today)
            ->orderBy('punch_time', 'asc')
            ->get();

        $todayHours = $this->calculateHours($todayAttendance);

        // Get week's attendance
        $weekAttendance = Attendance::where('employee_id', $employee->id)
            ->whereBetween('shift_date', [$startOfWeek, $endOfWeek])
            ->orderBy('shift_date')
            ->orderBy('punch_time', 'asc')
            ->get();

        $weeklyHours = $this->calculateHours($weekAttendance);
        $daysWorked = $weekAttendance->pluck('shift_date')->unique()->count();
        $avgHoursPerDay = $daysWorked > 0 ? round($weeklyHours / $daysWorked, 1) : 0;

        // Calculate daily hours for sparkline chart (last 7 days)
        $dailyHours = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayAttendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('shift_date', $date)
                ->orderBy('punch_time', 'asc')
                ->get();
            $dailyHours[] = round($this->calculateHours($dayAttendance), 0);
        }

        $hoursRemaining = max(0, 40 - $weeklyHours);

        return [
            Stat::make('Hours Today', number_format($todayHours, 1))
                ->description($todayAttendance->count().' punches')
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),

            Stat::make('Hours This Week', number_format($weeklyHours, 1))
                ->description($weeklyHours >= 40 ? 'Full week reached' : number_format($hoursRemaining, 1).' remaining')
                ->descriptionIcon($weeklyHours >= 40 ? 'heroicon-m-check-circle' : 'heroicon-m-arrow-trending-up')
                ->chart($dailyHours)
                ->color($weeklyHours >= 40 ? 'success' : 'info'),

            Stat::make('Days Worked', $daysWorked)
                ->description('This week')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),

            Stat::make('Avg Hours/Day', number_format($avgHoursPerDay, 1))
                ->description('Per day worked')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($avgHoursPerDay >= 8 ? 'success' : 'gray'),
        ];
    }

    /**
     * Calculate total hours from attendance records.
     */
    private function calculateHours($attendance): float
    {
        $hours = 0;
        $startPunch = null;

        foreach ($attendance->sortBy('punch_time') as $punch) {
            if ($punch->punch_state === 'start') {
                $startPunch = $punch;
            } elseif ($punch->punch_state === 'stop' && $startPunch) {
                $start = Carbon::parse($startPunch->punch_time);
                $end = Carbon::parse($punch->punch_time);
                $hours += $start->diffInMinutes($end) / 60;
                $startPunch = null;
            }
        }

        return round($hours, 2);
    }
}
