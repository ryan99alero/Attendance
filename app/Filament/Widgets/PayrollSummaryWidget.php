<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PayrollSummaryWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '120s';

    protected function getStats(): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $today = Carbon::today();

        // Get attendance for this week
        $weeklyAttendance = Attendance::whereBetween('shift_date', [$startOfWeek, $today])
            ->orderBy('employee_id')
            ->orderBy('punch_time')
            ->get();

        // Calculate total hours
        $totalHours = $this->calculateTotalHours($weeklyAttendance);

        // Get unique employees with time this week
        $employeesWithTime = $weeklyAttendance->pluck('employee_id')->unique()->count();

        // Average hours per employee
        $avgHours = $employeesWithTime > 0 ? round($totalHours / $employeesWithTime, 1) : 0;

        // Estimate gross pay (using average pay rate)
        $avgPayRate = Employee::where('is_active', true)
            ->whereNotNull('pay_rate')
            ->avg('pay_rate') ?? 15.00;
        $estimatedGrossPay = $totalHours * $avgPayRate;

        // Daily hours for sparkline chart
        $dailyHours = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayAttendance = Attendance::whereDate('shift_date', $date)
                ->orderBy('employee_id')
                ->orderBy('punch_time')
                ->get();
            $dailyHours[] = round($this->calculateTotalHours($dayAttendance), 0);
        }

        // Overtime hours (hours over 40 per employee)
        $overtimeHours = $this->calculateOvertimeHours($weeklyAttendance);

        return [
            Stat::make('Total Hours', number_format($totalHours, 1))
                ->description('This week')
                ->descriptionIcon('heroicon-m-clock')
                ->chart($dailyHours)
                ->color('primary'),

            Stat::make('Employees', $employeesWithTime)
                ->description('With time entries')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Avg Hours/Employee', number_format($avgHours, 1))
                ->description($avgHours >= 40 ? 'Full time avg' : 'Part time avg')
                ->descriptionIcon($avgHours >= 40 ? 'heroicon-m-check-circle' : 'heroicon-m-clock')
                ->color($avgHours >= 40 ? 'success' : 'warning'),

            Stat::make('Est. Gross Pay', '$'.number_format($estimatedGrossPay, 0))
                ->description('Based on avg rate $'.number_format($avgPayRate, 2))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Overtime Hours', number_format($overtimeHours, 1))
                ->description($overtimeHours > 0 ? 'Hours over 40/week' : 'No overtime')
                ->descriptionIcon($overtimeHours > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overtimeHours > 0 ? 'warning' : 'success'),

            Stat::make('Regular Hours', number_format(max(0, $totalHours - $overtimeHours), 1))
                ->description('Standard hours')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('gray'),
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

    /**
     * Calculate overtime hours (hours over 40 per employee).
     */
    private function calculateOvertimeHours($attendance): float
    {
        $overtimeHours = 0;
        $groupedByEmployee = $attendance->groupBy('employee_id');

        foreach ($groupedByEmployee as $employeeAttendance) {
            $employeeHours = 0;
            $startPunch = null;

            foreach ($employeeAttendance->sortBy('punch_time') as $punch) {
                if ($punch->punch_state === 'start') {
                    $startPunch = $punch;
                } elseif ($punch->punch_state === 'stop' && $startPunch) {
                    $start = Carbon::parse($startPunch->punch_time);
                    $end = Carbon::parse($punch->punch_time);
                    $employeeHours += $start->diffInMinutes($end) / 60;
                    $startPunch = null;
                }
            }

            if ($employeeHours > 40) {
                $overtimeHours += ($employeeHours - 40);
            }
        }

        return $overtimeHours;
    }
}
