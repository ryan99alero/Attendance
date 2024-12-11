<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use App\Models\Punch;

class PunchSummary extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table';
    protected static string $view = 'filament.pages.punch-summary';

    public $payPeriodId; // Bound to the select dropdown
    public $groupedPunches;

    public function mount()
    {
        $this->payPeriodId = null; // Default to no filter
        $this->groupedPunches = $this->fetchPunches(); // Fetch punches initially
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('payPeriodId')
                ->label('Select Pay Period')
                ->options($this->getPayPeriods()) // Options for the dropdown
                ->reactive()
                ->afterStateUpdated(fn () => $this->updatePunches()) // Refresh data on selection
                ->placeholder('All Pay Periods'),
        ];
    }

    protected function getPayPeriods(): array
    {
        // Ensure PayPeriod model exists and contains data
        return \App\Models\PayPeriod::query()
            ->select('id', 'start_date', 'end_date')
            ->get()
            ->pluck('start_date', 'id') // Make sure 'name' exists for each record
            ->toArray();
    }

    public function fetchPunches(): Collection
    {
        $query = Punch::select([
            'employee_id',
            \DB::raw("DATE(punch_time) as punch_date"),
            \DB::raw("
                MAX(CASE WHEN punch_type_id = 1 THEN TIME(punch_time) END) as ClockIn,
                MAX(CASE WHEN punch_type_id = 8 THEN TIME(punch_time) END) as LunchStart,
                MAX(CASE WHEN punch_type_id = 9 THEN TIME(punch_time) END) as LunchStop,
                MAX(CASE WHEN punch_type_id = 2 THEN TIME(punch_time) END) as ClockOut
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

    public function updatePunches()
    {
        $this->groupedPunches = $this->fetchPunches();
    }
}
