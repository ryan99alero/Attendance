<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use App\Models\Attendance;
use App\Models\PayPeriod;
use Illuminate\Support\Facades\DB;

class AttendanceSummary extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table';
    protected static string $view = 'filament.pages.attendance-summary';
    protected static ?string $navigationLabel = 'Attendance Summary';
    protected static bool $shouldRegisterNavigation = false;

    public $payPeriodId; // Bound to the select dropdown
    public $groupedAttendances;

    public function mount(): void
    {
        $this->payPeriodId = null; // Default to no filter
        $this->groupedAttendances = $this->fetchAttendances(); // Fetch initial attendance data
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('payPeriodId')
                ->label('Select Pay Period')
                ->options($this->getPayPeriods())
                ->reactive()
                ->afterStateUpdated(fn () => $this->updateAttendances())
                ->placeholder('All Pay Periods'),
        ];
    }

    protected function getPayPeriods(): array
    {
        return PayPeriod::query()
            ->select('id', 'start_date', 'end_date')
            ->get()
            ->mapWithKeys(fn ($period) => [
                $period->id => $period->start_date . ' to ' . $period->end_date,
            ])
            ->toArray();
    }

    public function fetchAttendances(): Collection
    {
        $query = Attendance::with('employee:id,full_names,external_id') // Eager load employee relationship
        ->select([
            'employee_id',
            DB::raw("DATE(punch_time) as attendance_date"),
            DB::raw("
                MAX(CASE WHEN punch_type_id = 1 THEN TIME(punch_time) END) as clock_in,
                MAX(CASE WHEN punch_type_id = 3 THEN TIME(punch_time) END) as lunch_start,
                MAX(CASE WHEN punch_type_id = 4 THEN TIME(punch_time) END) as lunch_stop,
                MAX(CASE WHEN punch_type_id = 2 THEN TIME(punch_time) END) as clock_out,
                COUNT(*) as total_punches
            "),
        ])
            ->groupBy('employee_id', DB::raw('DATE(punch_time)'))
            ->orderBy('employee_id')
            ->orderBy(DB::raw('DATE(punch_time)'));

        // Filter by pay period (start_date and end_date)
        if ($this->payPeriodId) {
            $payPeriod = PayPeriod::find($this->payPeriodId);

            if ($payPeriod) {
                $query->whereBetween(DB::raw('DATE(punch_time)'), [$payPeriod->start_date, $payPeriod->end_date]);
            }
        }

        // Retrieve results and filter incomplete rows
        return $query->get()->filter(function ($attendance) {
            $fields = [
                $attendance->clock_in,
                $attendance->lunch_start,
                $attendance->lunch_stop,
                $attendance->clock_out,
            ];

            // Count the number of non-null fields
            $filledFields = array_filter($fields, fn($field) => !is_null($field));

            // Include rows where the number of filled fields is 1 or 3
            return count($filledFields) === 1 || count($filledFields) === 3;
        })->map(function ($attendance) {
            $employee = $attendance->employee;
            return [
                'employee_id' => $attendance->employee_id,
                'FullName' => $employee?->full_names ?? 'N/A',
                'PayrollID' => $employee?->external_id ?? 'N/A',
                'attendance_date' => $attendance->attendance_date,
                'FirstPunch' => $attendance->clock_in,
                'LunchStart' => $attendance->lunch_start,
                'LunchStop' => $attendance->lunch_stop,
                'LastPunch' => $attendance->clock_out,
                'ManualEntries' => $attendance->manual_entries ?? 0,
                'TotalPunches' => $attendance->total_punches ?? 0,
            ];
        });
    }

    public function updateAttendances(): void
    {
        $this->groupedAttendances = $this->fetchAttendances();
    }
}
