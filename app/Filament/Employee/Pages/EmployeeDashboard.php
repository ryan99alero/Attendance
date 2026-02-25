<?php

namespace App\Filament\Employee\Pages;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PunchType;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class EmployeeDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'My Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.employee.pages.employee-dashboard';

    protected static ?string $slug = '';

    public ?Employee $employee = null;

    /**
     * Punch type IDs mapped to their state (start/stop).
     */
    protected array $punchTypeStates = [
        1 => 'start',  // Clock In
        2 => 'stop',   // Clock Out
        3 => 'stop',   // Lunch Start (leaving)
        4 => 'start',  // Lunch Stop (returning)
        5 => 'stop',   // Break Start (leaving)
        6 => 'start',  // Break End (returning)
    ];

    public function mount(): void
    {
        $this->employee = Auth::user()->employee;
    }

    /**
     * Get today's attendance records for the employee.
     */
    public function getTodayAttendance(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->employee) {
            return collect();
        }

        return Attendance::where('employee_id', $this->employee->id)
            ->whereDate('shift_date', Carbon::today())
            ->orderBy('punch_time', 'asc')
            ->get();
    }

    /**
     * Get this week's attendance records.
     */
    public function getWeekAttendance(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->employee) {
            return collect();
        }

        return Attendance::where('employee_id', $this->employee->id)
            ->whereBetween('shift_date', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ])
            ->orderBy('shift_date', 'desc')
            ->orderBy('punch_time', 'asc')
            ->get();
    }

    /**
     * Calculate total hours worked this week from punch pairs.
     */
    public function getWeeklyHours(): float
    {
        $attendance = $this->getWeekAttendance();

        $totalHours = 0;
        $groupedByDate = $attendance->groupBy('shift_date');

        foreach ($groupedByDate as $date => $punches) {
            $dayHours = $this->calculateDayHours($punches);
            $totalHours += $dayHours;
        }

        return $totalHours;
    }

    /**
     * Calculate hours for a single day from punch records.
     */
    public function calculateDayHours($punches): float
    {
        $hours = 0;
        $startPunch = null;

        foreach ($punches->sortBy('punch_time') as $punch) {
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

    /**
     * Get unique days worked this week.
     */
    public function getDaysWorked(): int
    {
        return $this->getWeekAttendance()->pluck('shift_date')->unique()->count();
    }

    /**
     * Check if employee can punch via web portal.
     */
    public function canWebPunch(): bool
    {
        return $this->employee?->portal_clockin ?? false;
    }

    /**
     * Get the last attendance record to determine current state.
     */
    public function getLastPunch(): ?Attendance
    {
        if (! $this->employee) {
            return null;
        }

        return Attendance::where('employee_id', $this->employee->id)
            ->whereDate('shift_date', Carbon::today())
            ->orderBy('punch_time', 'desc')
            ->first();
    }

    /**
     * Determine if employee is currently clocked in.
     */
    public function isClockedIn(): bool
    {
        $lastPunch = $this->getLastPunch();

        if (! $lastPunch) {
            return false;
        }

        return $lastPunch->punch_state === 'start';
    }

    /**
     * Get current punch context for smart button display.
     * Returns state info to determine which buttons to show prominently.
     *
     * @return array{state: string, primary_action: int, secondary_actions: array, message: string, color: string}
     */
    public function getCurrentPunchContext(): array
    {
        $lastPunch = $this->getLastPunch();

        if (! $lastPunch) {
            return [
                'state' => 'not_clocked_in',
                'primary_action' => 1, // Clock In
                'secondary_actions' => [],
                'message' => 'Ready to start your day',
                'color' => 'emerald',
            ];
        }

        $punchTypeId = $lastPunch->punch_type_id;
        $punchState = $lastPunch->punch_state;
        $punchTime = Carbon::parse($lastPunch->punch_time)->format('g:i A');

        // On lunch (punch_type 3 = Lunch Start, state = stop)
        if ($punchTypeId === 3 && $punchState === 'stop') {
            return [
                'state' => 'on_lunch',
                'primary_action' => 4, // Lunch Stop (return from lunch)
                'secondary_actions' => [2], // Clock Out
                'message' => 'On lunch since '.$punchTime,
                'color' => 'amber',
            ];
        }

        // On break (punch_type 5 = Break Start, state = stop)
        if ($punchTypeId === 5 && $punchState === 'stop') {
            return [
                'state' => 'on_break',
                'primary_action' => 6, // Break End (return from break)
                'secondary_actions' => [2], // Clock Out
                'message' => 'On break since '.$punchTime,
                'color' => 'violet',
            ];
        }

        // Clocked in and working
        if ($punchState === 'start') {
            return [
                'state' => 'working',
                'primary_action' => 2, // Clock Out
                'secondary_actions' => [3, 5], // Lunch Out, Break Out
                'message' => 'Working since '.$punchTime,
                'color' => 'emerald',
            ];
        }

        // Clocked out
        return [
            'state' => 'clocked_out',
            'primary_action' => 1, // Clock In
            'secondary_actions' => [],
            'message' => 'Clocked out at '.$punchTime,
            'color' => 'gray',
        ];
    }

    /**
     * Get punch type details for button display.
     */
    public function getPunchTypeDetails(int $punchTypeId): array
    {
        $types = [
            1 => ['name' => 'Clock In', 'icon' => 'heroicon-o-arrow-right-start-on-rectangle', 'color' => 'emerald'],
            2 => ['name' => 'Clock Out', 'icon' => 'heroicon-o-arrow-right-end-on-rectangle', 'color' => 'rose'],
            3 => ['name' => 'Lunch Out', 'icon' => 'heroicon-o-arrow-right-end-on-rectangle', 'color' => 'amber'],
            4 => ['name' => 'Lunch In', 'icon' => 'heroicon-o-arrow-right-start-on-rectangle', 'color' => 'amber'],
            5 => ['name' => 'Break Out', 'icon' => 'heroicon-o-pause-circle', 'color' => 'violet'],
            6 => ['name' => 'Break In', 'icon' => 'heroicon-o-play-circle', 'color' => 'violet'],
        ];

        return $types[$punchTypeId] ?? ['name' => 'Unknown', 'icon' => 'heroicon-o-question-mark-circle', 'color' => 'gray'];
    }

    /**
     * Get available punch types for the form.
     */
    protected function getPunchTypeOptions(): array
    {
        return PunchType::whereIn('id', [1, 2, 3, 4, 5, 6])
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        // Removed - punch buttons are now inline on the dashboard
        return [];
    }

    /**
     * Quick punch from button click (no modal).
     */
    public function quickPunch(int $punchTypeId): void
    {
        $this->recordPunch($punchTypeId, null);
    }

    protected function recordPunch(int $punchTypeId, ?string $notes = null): void
    {
        if (! $this->canWebPunch() || ! $this->employee) {
            Notification::make()
                ->title('Not Authorized')
                ->body('You are not authorized to punch via web.')
                ->danger()
                ->send();

            return;
        }

        // Determine punch state based on type
        $punchState = $this->punchTypeStates[$punchTypeId] ?? 'unknown';

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'punch_time' => now(),
            'shift_date' => now()->toDateString(),
            'punch_type_id' => $punchTypeId,
            'punch_state' => $punchState,
            'is_manual' => true,
            'status' => 'Incomplete',
            'issue_notes' => $notes,
        ]);

        $punchType = PunchType::find($punchTypeId);

        Notification::make()
            ->title('Time Recorded')
            ->body("{$punchType->name} recorded at ".now()->format('g:i A'))
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }
}
