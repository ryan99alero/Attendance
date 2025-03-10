<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use App\Models\Attendance;
use App\Models\PayPeriod;
use Illuminate\Support\Facades\DB;
use App\Services\AttendanceProcessing\AttendanceProcessingService;
use App\Services\AttendanceProcessing\AttendanceStatusUpdateService;

class AttendanceSummary extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table';
    protected static string $view = 'filament.pages.attendance-summary';
    protected static ?string $navigationLabel = 'Attendance Summary';
    protected static bool $shouldRegisterNavigation = false;

    public $payPeriodId;
    public $search = '';
    public $statusFilter = 'NeedsReview';
    public $groupedAttendances;
    public array $selectedAttendances = [];
    public bool $selectAll = false;
    public bool $autoProcess = false;

    public $sortColumn = 'attendance_date';
    public $sortDirection = 'asc';

    public function mount(): void
    {
        Log::info('[AttendanceSummary] Entering function: ' . __FUNCTION__, [
            'parameters' => func_get_args()
        ]);
        $this->payPeriodId = null;
        $this->search = '';
        $this->groupedAttendances = collect();
    }

    protected function getFormSchema(): array
    {
        Log::info('[AttendanceSummary] Entering function: ' . __FUNCTION__, [
            'parameters' => func_get_args()
        ]);
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
                    'Migrated' => 'Migrated',
                    'problem' => 'Problem (All Except Migrated)',
                ])
                ->default('problem')
                ->reactive()
                ->afterStateUpdated(fn () => $this->updateAttendances()),
        ];
    }

    protected function getPayPeriods(): array
    {
        Log::info('[AttendanceSummary] Entering function: ' . __FUNCTION__, [
            'parameters' => func_get_args()
        ]);
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
        Log::info('[AttendanceSummary] Entering function: ' . __FUNCTION__, [
            'parameters' => func_get_args()
        ]);
        if (!$this->payPeriodId) {
            return collect();
        }

        $payPeriod = PayPeriod::find($this->payPeriodId);

        if (!$payPeriod) {
            return collect();
        }

        $columnMap = [
            'FullName' => 'employees.full_names',
            'attendance_date' => 'attendance_date',
            'FirstPunch' => 'clock_in',
            'LunchStart' => 'lunch_start',
            'LunchStop' => 'lunch_stop',
            'LastPunch' => 'clock_out',
        ];

        $sortColumn = $columnMap[$this->sortColumn] ?? 'attendance_date';

        return Attendance::with('employee')
            ->select([
                DB::raw("GROUP_CONCAT(attendances.id) as attendance_ids"),
                DB::raw("ANY_VALUE(attendances.id) as attendanceId"),
                'attendances.employee_id',
                DB::raw("ANY_VALUE(attendances.device_id) as device_id"), // ✅ Ensure device_id is fetched
                'employees.full_names as FullName',
                'employees.external_id as PayrollID',
                DB::raw("ANY_VALUE(attendances.status) as status"),
                DB::raw("ANY_VALUE(attendances.punch_state) as punch_state"), // ✅ Ensure punch_state is fetched
                DB::raw("DATE(attendances.punch_time) as attendance_date"),
                DB::raw("MAX(CASE WHEN attendances.punch_type_id = 1 THEN TIME(attendances.punch_time) END) as clock_in"),
                DB::raw("MAX(CASE WHEN attendances.punch_type_id = 3 THEN TIME(attendances.punch_time) END) as lunch_start"),
                DB::raw("MAX(CASE WHEN attendances.punch_type_id = 4 THEN TIME(attendances.punch_time) END) as lunch_stop"),
                DB::raw("MAX(CASE WHEN attendances.punch_type_id = 2 THEN TIME(attendances.punch_time) END) as clock_out"),
                DB::raw("COUNT(*) as total_punches"),
                DB::raw("SUM(CASE WHEN attendances.is_manual = 1 THEN 1 ELSE 0 END) as manual_entries")
            ])
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->whereBetween(DB::raw('DATE(attendances.punch_time)'), [$payPeriod->start_date, $payPeriod->end_date])
            ->when($this->statusFilter !== 'all', function ($q) {
                if ($this->statusFilter === 'problem') {
                    $q->where('attendances.status', '!=', 'Migrated');
                } else {
                    $q->where('attendances.status', $this->statusFilter);
                }
            })
            ->groupBy('attendances.employee_id', 'attendance_date')
            ->orderBy($sortColumn, $this->sortDirection)
            ->get()
            ->map(fn ($attendance) => [
                'attendance_ids' => explode(',', $attendance->attendance_ids),
                'attendanceId' => $attendance->attendanceId,
                'employee_id' => $attendance->employee_id,
                'device_id' => $attendance->device_id ?? null, // ✅ Ensuring it is available
                'FullName' => $attendance->FullName ?? 'N/A',
                'PayrollID' => $attendance->PayrollID ?? 'N/A',
                'attendance_date' => $attendance->attendance_date,
                'FirstPunch' => $attendance->clock_in,
                'LunchStart' => $attendance->lunch_start,
                'LunchStop' => $attendance->lunch_stop,
                'LastPunch' => $attendance->clock_out,
                'punch_state' => $attendance->punch_state ?? null, // ✅ Ensuring punch_state is available
                'status' => $attendance->status,
            ]);
    }

    public function updateAttendances(): void
    {
        Log::info('[AttendanceSummary] Entering function: ' . __FUNCTION__, [
            'parameters' => func_get_args()
        ]);
        $this->groupedAttendances = $this->fetchAttendances();
    }

    public function sortBy($field): void
    {
        Log::info('[AttendanceSummary] Entering function: ' . __FUNCTION__, [
            'parameters' => func_get_args()
        ]);
        $columnMap = [
            'FullName' => 'employees.full_names',
            'attendance_date' => 'attendance_date',
            'FirstPunch' => 'clock_in',
            'LunchStart' => 'lunch_start',
            'LunchStop' => 'lunch_stop',
            'LastPunch' => 'clock_out',
        ];

        if (!isset($columnMap[$field])) {
            return;
        }

        $this->sortDirection = ($this->sortColumn === $field && $this->sortDirection === 'asc') ? 'desc' : 'asc';
        $this->sortColumn = $columnMap[$field];
        $this->updateAttendances();
    }

//    public function openEditModal(array $data = []): void
//    {
//        Log::info('[AttendanceSummary] Entering function: ' . __FUNCTION__, [
//            'parameters' => func_get_args()
//        ]);
//        $attendanceId = $data['attendanceId'] ?? null;
//        $employeeId = $data['employeeId'] ?? null;
//        $date = $data['date'] ?? null;
//        $punchType = $data['punchType'] ?? null;
//        $existingTime = $data['existingTime'] ?? null;
//        $punchState = $data['punchState'] ?? null; // ✅ Added punchState
//
//        if (!$attendanceId || !$employeeId || !$date || !$punchType) {
//            Log::error('[AttendanceSummary] Missing required parameters for openEditModal', [
//                'attendanceId' => $attendanceId,
//                'employeeId' => $employeeId,
//                'date' => $date,
//                'punchType' => $punchType,
//                'existingTime' => $existingTime,
//                'punchState' => $punchState, // ✅ Log punchState
//                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
//            ]);
//            return;
//        }
//
//        Log::info('[AttendanceSummary] Opening Edit Modal with', [
//            'attendanceId' => $attendanceId,
//            'employeeId' => $employeeId,
//            'date' => $date,
//            'punchType' => $punchType,
//            'existingTime' => $existingTime,
//            'punchState' => $punchState, // ✅ Log punchState
//        ]);
//
//        $this->dispatch('open-update-modal', [
//            'attendanceId' => $attendanceId,
//            'employeeId' => $employeeId,
//            'date' => $date,
//            'punchType' => $punchType,
//            'existingTime' => $existingTime,
//            'punchState' => $punchState, // ✅ Now passing punchState
//        ]);
//    }

    protected function getActions(): array
    {
        Log::info('[AttendanceSummary] Entering function: ' . __FUNCTION__, [
            'parameters' => func_get_args()
        ]);
        return [
            Action::make('Add Time Record')
                ->label('Add Time Record')
                ->color('primary')
                ->icon('heroicon-o-plus')
                ->dispatch('open-create-modal'),
        ];

    }

    protected $listeners = [
        'processSelected' => 'processSelected',
        'timeRecordCreated' => 'refreshAttendanceData',
        'timeRecordUpdated' => 'refreshAttendanceData',
        'open-update-modal' => 'openUpdateModal', // ✅ No need to change, this dispatches to UpdateTimeRecordModal
        'open-create-modal' => 'openCreateModal', // ✅ No need to change, this dispatches to CreateTimeRecordModal
    ];

    public function refreshAttendanceData(): void
    {
        Log::info('[AttendanceSummary] Entering function: ' . __FUNCTION__, [
            'parameters' => func_get_args()
        ]);
        $this->updateAttendances();
        Log::info('[AttendanceSummary] Exiting function: ' . __FUNCTION__, [
            'finalState' => $this->someVariable ?? 'N/A'
        ]);
        $this->dispatch('$refresh');
    }
}
