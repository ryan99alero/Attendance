<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use App\Models\Attendance;

class AttendanceSummary extends Page
{
//    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-table';
    protected static string $view = 'filament.pages.attendance-summary';
    protected static ?string $navigationLabel = 'Attendance Summary';

    public $payPeriodId; // Bound to the select dropdown
    public $groupedAttendances;

    public function mount()
    {
        $this->payPeriodId = null; // Default to no filter
        $this->groupedAttendances = $this->fetchAttendances(); // Fetch initial attendance data
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('payPeriodId')
                ->label('Select Pay Period')
                ->options($this->getPayPeriods()) // Options for the dropdown
                ->reactive()
                ->afterStateUpdated(fn () => $this->updateAttendances()) // Refresh data on selection
                ->placeholder('All Pay Periods'),
        ];
    }

    protected function getPayPeriods(): array
    {
        // Fetch pay periods from the database
        return \App\Models\PayPeriod::query()
            ->select('id', 'start_date', 'end_date')
            ->get()
            ->mapWithKeys(fn ($period) => [
                $period->id => $period->start_date . ' to ' . $period->end_date,
            ])
            ->toArray();
    }

    public function fetchAttendances(): Collection
    {
        $query = Attendance::select([
            'employee_id',
            \DB::raw("DATE(punch_time) as attendance_date"),
            \DB::raw("
                MIN(punch_time) as FirstPunch,
                MAX(punch_time) as LastPunch,
                SUM(CASE WHEN is_manual = 1 THEN 1 ELSE 0 END) as ManualEntries,
                COUNT(*) as TotalPunches
            "),
        ])
            ->groupBy('employee_id', \DB::raw('DATE(punch_time)'))
            ->orderBy('employee_id')
            ->orderBy(\DB::raw('DATE(punch_time)'));

        if ($this->payPeriodId) {
            $query->where('pay_period_id', $this->payPeriodId);
        }

        return $query->get();
    }

    public function updateAttendances()
    {
        $this->groupedAttendances = $this->fetchAttendances();
    }
}
