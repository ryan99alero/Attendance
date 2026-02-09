<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use App\Models\PayPeriod;
use App\Models\Punch;
use Illuminate\Support\Facades\DB;
use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class PunchSummary extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-table';
    protected string $view = 'filament.pages.punch-summary';
    protected static ?string $navigationLabel = 'Punch Summary';

    public ?array $data = [];
    public $groupedPunches;

    public function mount(): void
    {
        $this->form->fill([
            'payPeriodId' => null,
            'search' => '',
        ]);
        $this->groupedPunches = $this->fetchPunches();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('payPeriodId')
                    ->label('Select Pay Period')
                    ->options($this->getPayPeriods())
                    ->live()
                    ->afterStateUpdated(fn () => $this->updatePunches())
                    ->placeholder('All Pay Periods'),
                TextInput::make('search')
                    ->label('Search by Name or Payroll ID')
                    ->placeholder('Enter employee name or payroll ID')
                    ->live(debounce: 500)
                    ->afterStateUpdated(fn () => $this->updatePunches()),
            ])
            ->statePath('data');
    }

    protected function getPayPeriods(): array
    {
        return PayPeriod::query()
            ->select('id', 'start_date', 'end_date')
            ->get()
            ->pluck('start_date', 'id')
            ->toArray();
    }

    public function fetchPunches(): Collection
    {
        $payPeriodId = $this->data['payPeriodId'] ?? null;
        $search = $this->data['search'] ?? '';

        $query = Punch::with('employee:id,full_names,external_id')
            ->select([
                'employee_id',
                DB::raw("DATE(punch_time) as punch_date"),
                DB::raw("
                    MAX(CASE WHEN punch_type_id = 1 THEN TIME(punch_time) END) as clock_in,
                    MAX(CASE WHEN punch_type_id = 3 THEN TIME(punch_time) END) as lunch_start,
                    MAX(CASE WHEN punch_type_id = 4 THEN TIME(punch_time) END) as lunch_stop,
                    MAX(CASE WHEN punch_type_id = 2 THEN TIME(punch_time) END) as clock_out
                "),
            ])
            ->groupBy('employee_id', DB::raw('DATE(punch_time)'))
            ->orderBy('employee_id')
            ->orderBy(DB::raw('DATE(punch_time)'));

        if ($payPeriodId) {
            $query->where('pay_period_id', $payPeriodId);
        }

        if ($search) {
            $query->whereHas('employee', function ($subQuery) use ($search) {
                $subQuery->where('full_names', 'like', '%' . $search . '%')
                    ->orWhere('external_id', 'like', '%' . $search . '%');
            });
        }

        $groupedPunches = $query->get();

        return $groupedPunches->map(function ($punch) {
            $employee = $punch->employee;
            return [
                'EmployeeID' => $punch->employee_id,
                'FullName' => $employee?->full_names ?? 'N/A',
                'PayrollID' => $employee?->external_id ?? 'N/A',
                'PunchDate' => $punch->punch_date,
                'ClockIn' => $punch->clock_in,
                'LunchStart' => $punch->lunch_start,
                'LunchStop' => $punch->lunch_stop,
                'ClockOut' => $punch->clock_out,
            ];
        });
    }

    public function updatePunches(): void
    {
        $this->groupedPunches = $this->fetchPunches();
    }
}
