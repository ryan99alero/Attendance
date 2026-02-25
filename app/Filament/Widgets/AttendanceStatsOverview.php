<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Device;
use App\Models\Employee;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AttendanceStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $startOfWeek = Carbon::now()->startOfWeek();

        // Total active employees
        $totalEmployees = Employee::where('is_active', true)->count();

        // Employees who clocked in today
        $presentToday = Attendance::whereDate('shift_date', $today)
            ->where('punch_state', 'start')
            ->distinct('employee_id')
            ->count('employee_id');

        $presentYesterday = Attendance::whereDate('shift_date', $yesterday)
            ->where('punch_state', 'start')
            ->distinct('employee_id')
            ->count('employee_id');

        // Calculate trend
        $presentTrend = $presentYesterday > 0
            ? round((($presentToday - $presentYesterday) / $presentYesterday) * 100, 1)
            : 0;

        // Absent today (active employees who haven't clocked in)
        $absentToday = $totalEmployees - $presentToday;

        // Total punches today
        $punchesToday = Attendance::whereDate('shift_date', $today)->count();
        $punchesYesterday = Attendance::whereDate('shift_date', $yesterday)->count();

        // Weekly punches for chart
        $weeklyPunches = Attendance::whereBetween('shift_date', [$startOfWeek, $today])
            ->select(DB::raw('DATE(shift_date) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();

        // Devices online (seen in last 5 minutes)
        $devicesOnline = Device::where('is_active', true)
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->count();
        $totalDevices = Device::where('is_active', true)->count();

        // Total hours this week
        $weeklyAttendance = Attendance::whereBetween('shift_date', [$startOfWeek, $today])
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get();

        $totalHoursThisWeek = $this->calculateTotalHours($weeklyAttendance);

        // Daily hours for chart (last 7 days)
        $dailyHours = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayAttendance = Attendance::whereDate('shift_date', $date)
                ->orderBy('employee_id')
                ->orderBy('punch_time')
                ->get();
            $dailyHours[] = round($this->calculateTotalHours($dayAttendance), 0);
        }

        return [
            Stat::make('Total Employees', $totalEmployees)
                ->description('Active employees')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Present Today', $presentToday.' / '.$totalEmployees)
                ->description($presentTrend >= 0 ? abs($presentTrend).'% vs yesterday' : abs($presentTrend).'% vs yesterday')
                ->descriptionIcon($presentTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($presentTrend >= 0 ? 'success' : 'danger')
                ->chart($weeklyPunches ?: [0]),

            Stat::make('Absent Today', $absentToday)
                ->description($totalEmployees > 0 ? round(($absentToday / $totalEmployees) * 100, 1).'% of workforce' : '0%')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($absentToday > 0 ? 'warning' : 'success'),

            Stat::make('Punches Today', number_format($punchesToday))
                ->description($punchesYesterday > 0 ? ($punchesToday >= $punchesYesterday ? 'Up from ' : 'Down from ').$punchesYesterday.' yesterday' : 'No data yesterday')
                ->descriptionIcon($punchesToday >= $punchesYesterday ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color('info'),

            Stat::make('Hours This Week', number_format($totalHoursThisWeek, 1))
                ->description('Total tracked hours')
                ->descriptionIcon('heroicon-m-clock')
                ->chart($dailyHours)
                ->color('success'),

            Stat::make('Devices Online', $devicesOnline.' / '.$totalDevices)
                ->description($totalDevices > 0 ? round(($devicesOnline / $totalDevices) * 100, 0).'% connected' : 'No devices')
                ->descriptionIcon('heroicon-m-computer-desktop')
                ->color($devicesOnline === $totalDevices ? 'success' : ($devicesOnline > 0 ? 'warning' : 'danger')),
        ];
    }

    /**
     * Calculate total hours from attendance records.
     */
    private function calculateTotalHours($attendance): float
    {
        $totalHours = 0;
        $groupedByEmployee = $attendance->groupBy('employee_id');

        foreach ($groupedByEmployee as $employeeAttendance) {
            $startPunch = null;

            foreach ($employeeAttendance->sortBy('punch_time') as $punch) {
                if ($punch->punch_state === 'start') {
                    $startPunch = $punch;
                } elseif ($punch->punch_state === 'stop' && $startPunch) {
                    $start = Carbon::parse($startPunch->punch_time);
                    $end = Carbon::parse($punch->punch_time);
                    $totalHours += $start->diffInMinutes($end) / 60;
                    $startPunch = null;
                }
            }
        }

        return $totalHours;
    }
}
