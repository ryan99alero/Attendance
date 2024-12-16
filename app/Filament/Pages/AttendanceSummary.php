<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use App\Models\Attendance;
use App\Models\PayPeriod;

class AttendanceSummary extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table';
    protected static string $view = 'filament.pages.attendance-summary';
    protected static ?string $navigationLabel = 'Attendance Summary';

    public $payPeriodId; // Bound to the select dropdown
    public $groupedAttendances;

    /**
     * Mount the page and initialize properties.
     */
    public function mount(): void
    {
        $this->payPeriodId = null; // Default to no filter
        $this->groupedAttendances = $this->fetchAttendances(); // Fetch initial attendance data
    }

    /**
     * Fetch pay periods for the dropdown options.
     */
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

    /**
     * Fetch attendances grouped by employee and date.
     */
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

    /**
     * Reactively update attendances when the pay period changes.
     */
    public function updateAttendances(): void
    {
        $this->groupedAttendances = $this->fetchAttendances();
    }

    /**
     * Fetch status ENUM values from the database.
     */
    protected function getStatusOptions(): array
    {
        $type = \DB::select(\DB::raw("SHOW COLUMNS FROM attendances WHERE Field = 'status'"))[0]->Type;

        preg_match('/enum\((.*)\)$/', $type, $matches);
        $enumValues = str_getcsv($matches[1], ',', "'");

        return array_combine($enumValues, $enumValues);
    }

    /**
     * Define the form schema for the pay period filter.
     */
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

    /**
     * Define the table for attendance records.
     */
    protected function getTableSchema(): array
    {
        return [
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options($this->getStatusOptions())
                ->reactive(),
        ];
    }
}
