<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Get current date for calculations
        $today = Carbon::today();
        $thisWeekStart = Carbon::now()->startOfWeek();
        $thisMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Total active employees
        $totalEmployees = Employee::where('is_active', true)->count();

        // Today's punches
        $todaysPunches = Attendance::whereDate('punch_time', $today)
            ->where('status', '!=', 'deleted')
            ->count();

        // This week's total hours (approximate based on punch count)
        $thisWeekPunches = Attendance::whereBetween('punch_time', [$thisWeekStart, Carbon::now()])
            ->where('status', '!=', 'deleted')
            ->count();
        $thisWeekHours = round($thisWeekPunches * 0.25, 1); // Approximate 15 min intervals

        // This month vs last month attendance comparison
        $thisMonthPunches = Attendance::whereBetween('punch_time', [$thisMonthStart, Carbon::now()])
            ->where('status', '!=', 'deleted')
            ->count();

        $lastMonthPunches = Attendance::whereBetween('punch_time', [$lastMonthStart, $lastMonthEnd])
            ->where('status', '!=', 'deleted')
            ->count();

        $monthlyChange = $lastMonthPunches > 0
            ? round((($thisMonthPunches - $lastMonthPunches) / $lastMonthPunches) * 100, 1)
            : 0;

        // Department count
        $totalDepartments = Department::count();

        // Average daily attendance rate
        $averageDailyAttendance = $totalEmployees > 0
            ? round(($todaysPunches / 4) / $totalEmployees * 100, 1) // Assuming 4 punches per day average
            : 0;

        return [
            Stat::make('Active Employees', $totalEmployees)
                ->description('Total registered employees')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Today\'s Punches', $todaysPunches)
                ->description('Clock in/out events today')
                ->descriptionIcon('heroicon-m-clock')
                ->color('success'),

            Stat::make('This Week Hours', $thisWeekHours)
                ->description('Estimated total hours')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),

            Stat::make('Monthly Activity', $thisMonthPunches)
                ->description($monthlyChange >= 0 ? "{$monthlyChange}% increase" : "{$monthlyChange}% decrease")
                ->descriptionIcon($monthlyChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthlyChange >= 0 ? 'success' : 'danger'),

            Stat::make('Departments', $totalDepartments)
                ->description('Active departments')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info'),

            Stat::make('Attendance Rate', $averageDailyAttendance . '%')
                ->description('Estimated daily attendance')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($averageDailyAttendance >= 80 ? 'success' : ($averageDailyAttendance >= 60 ? 'warning' : 'danger')),
        ];
    }

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 3;
    }
}
