<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Pages\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use App\Models\Attendance;
use App\Models\PayPeriod;
use Illuminate\Support\Facades\DB;
use App\Services\AttendanceProcessing\AttendanceProcessingService;

class AttendanceSummary extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table';
    protected static string $view = 'filament.pages.attendance-summary';
    protected static ?string $navigationLabel = 'Attendance Summary';
    protected static bool $shouldRegisterNavigation = false;

    public $payPeriodId;
    public $search = '';
    public $statusFilter = 'NeedsReview'; // Default filter
    public $groupedAttendances;
    public array $selectedAttendances = [];
    public bool $selectAll = false;
    public bool $autoProcess = false;

    public function mount(): void
    {
        $this->payPeriodId = null;
        $this->search = '';
        $this->groupedAttendances = collect();
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

            Forms\Components\TextInput::make('search')
                ->label('Search')
                ->placeholder('Search any value...')
                ->reactive()
                ->afterStateUpdated(fn () => $this->updateAttendances()),

            Forms\Components\Select::make('statusFilter')
                ->label('Filter by Status')
                ->options([
                    'all' => 'All',
                    'NeedsReview' => 'Needs Review',
                    'Incomplete' => 'Incomplete',
                    'Complete' => 'Complete',
                    'Partial' => 'Partial',
                    'Error' => 'Error',
                    'Migrated' => 'Migrated',
                ])
                ->default('NeedsReview')
                ->reactive()
                ->afterStateUpdated(fn () => $this->updateAttendances()),

            Forms\Components\Toggle::make('autoProcess')
                ->label('Auto-Process Completed Records')
                ->reactive(),
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
        if (!$this->payPeriodId) {
            \Log::info("No PayPeriod selected. Returning empty attendance records.");
            return collect();
        }

        $payPeriod = PayPeriod::find($this->payPeriodId);

        if (!$payPeriod) {
            \Log::warning("Invalid PayPeriod ID: {$this->payPeriodId}. Returning empty attendance records.");
            return collect();
        }

        \Log::info("Fetching attendances for PayPeriod: {$payPeriod->start_date} to {$payPeriod->end_date}");

        $query = Attendance::with('employee')
            ->select([
                DB::raw("GROUP_CONCAT(attendances.id) as attendance_ids"),
                'attendances.employee_id',
                'employees.full_names as FullName',
                'employees.external_id as PayrollID',
                DB::raw("ANY_VALUE(attendances.status) as status"),
                DB::raw("DATE(attendances.punch_time) as attendance_date"),
                DB::raw("
                MAX(CASE WHEN attendances.punch_type_id = 1 THEN TIME(attendances.punch_time) END) as clock_in,
                MAX(CASE WHEN attendances.punch_type_id = 3 THEN TIME(attendances.punch_time) END) as lunch_start,
                MAX(CASE WHEN attendances.punch_type_id = 4 THEN TIME(attendances.punch_time) END) as lunch_stop,
                MAX(CASE WHEN attendances.punch_type_id = 2 THEN TIME(attendances.punch_time) END) as clock_out,
                COUNT(*) as total_punches
            "),
                DB::raw("SUM(CASE WHEN attendances.is_manual = 1 THEN 1 ELSE 0 END) as manual_entries")
            ])
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->whereBetween(DB::raw('DATE(attendances.punch_time)'), [$payPeriod->start_date, $payPeriod->end_date])
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('attendances.status', $this->statusFilter))
            ->groupBy('attendances.employee_id', 'attendance_date')
            ->orderBy('attendances.employee_id')
            ->orderBy(DB::raw('DATE(attendances.punch_time)'))
            ->get();

        \Log::info("Fetched {$query->count()} attendance records.");

        return $query->map(fn ($attendance) => [
            'attendance_ids' => explode(',', $attendance->attendance_ids),
            'employee_id' => $attendance->employee_id,
            'FullName' => $attendance->FullName ?? 'N/A',
            'PayrollID' => $attendance->PayrollID ?? 'N/A',
            'attendance_date' => $attendance->attendance_date,
            'FirstPunch' => $attendance->clock_in,
            'LunchStart' => $attendance->lunch_start,
            'LunchStop' => $attendance->lunch_stop,
            'LastPunch' => $attendance->clock_out,
            'ManualEntries' => $attendance->manual_entries ?? 0,
            'TotalPunches' => $attendance->total_punches ?? 0,
            'status' => $attendance->status,
        ]);
    }

    public function updateAttendances(): void
    {
        $this->groupedAttendances = $this->fetchAttendances();
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedAttendances = collect($this->groupedAttendances)->pluck('id')->toArray();
        } else {
            $this->selectedAttendances = [];
        }

        Log::info("📌 Select All Updated. Selected Attendances: " . json_encode($this->selectedAttendances));
    }

    public function processSelected(): void
    {
        Log::info("🛠 [processSelected] Button clicked. Raw Selected Records: " . json_encode($this->selectedAttendances));

        if (empty($this->selectedAttendances)) {
            Log::warning("⚠️ No records selected.");
            return;
        }

        // ✅ Flatten and convert selected IDs into an array of integers
        $attendanceIds = collect($this->selectedAttendances)
            ->map(fn ($idString) => explode(',', $idString)) // Split comma-separated strings into arrays
            ->flatten() // Flatten to a single-dimensional array
            ->map(fn ($id) => (int) trim($id)) // Convert all values to integers
            ->unique() // Ensure uniqueness
            ->values()
            ->toArray();

        Log::info("🔍 [processSelected] Processed Attendance IDs: " . json_encode($attendanceIds));

        if (empty($attendanceIds)) {
            Log::warning("⚠️ No valid attendance IDs extracted.");
            return;
        }

        // ✅ Update the attendance records to 'Complete'
        $this->updateAttendanceStatus($attendanceIds, 'Complete');

        // ✅ Run additional processing if Auto-Process is enabled
        if ($this->autoProcess) {
            Log::info("🔄 Auto-Processing enabled. Running AttendanceProcessingService.");
            app(AttendanceProcessingService::class)->processCompletedAttendanceRecords($attendanceIds);
        }

        Log::info("✅ [processSelected] Attendance records marked as Complete.");

        // ✅ Refresh attendance list
        $this->updateAttendances();
    }

    private function updateAttendanceStatus(array $attendanceIds, string $newStatus): void
    {
        $filteredIds = array_filter($attendanceIds);

        if (empty($filteredIds)) {
            Log::warning("⚠️ [updateAttendanceStatus] No valid attendance IDs found.");
            return;
        }

        Attendance::whereIn('id', $filteredIds)->update(['status' => $newStatus]);

        Log::info("✅ [updateAttendanceStatus] Updated " . count($filteredIds) . " attendance records to status: {$newStatus}");
    }

    protected function getActions(): array
    {
        return [
            Action::make('Add Time Record')
                ->label('Add Time Record')
                ->color('primary')
                ->icon('heroicon-o-plus')
                ->url(route('filament.admin.resources.attendances.create'))
                ->openUrlInNewTab(),
        ];
    }

    protected $listeners = [
        'processSelected' => 'processSelected',
        'timeRecordCreated' => 'refreshAttendanceData',
    ];

    public function refreshAttendanceData(): void
    {
        $this->updateAttendances();
    }
}
