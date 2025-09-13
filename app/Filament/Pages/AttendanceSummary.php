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

class AttendanceSummary extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table';
    protected static string $view = 'filament.pages.attendance-summary';
    protected static ?string $navigationLabel = 'Attendance Summary';
    protected static bool $shouldRegisterNavigation = false;

    public $payPeriodId;
    public $search = '';
    public $statusFilter = 'NeedsReview';
    public $duplicatesFilter = 'all';
    public $groupedAttendances;
    public array $selectedAttendances = [];
    public bool $selectAll = false;
    public bool $autoProcess = false;

    public $sortColumn = 'shift_date';
    public $sortDirection = 'asc';

    public function mount(): void
    {
        Log::info("[AttendanceSummary] mount() called - Initializing component.");
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
                    'Migrated' => 'Migrated',
                    'problem' => 'Problem (All Except Migrated)',
                ])
                ->default('problem')
                ->reactive()
                ->afterStateUpdated(fn () => $this->updateAttendances()),

            Forms\Components\Select::make('duplicatesFilter')
                ->label('Filter by Issues')
                ->options([
                    'all' => 'All',
                    'duplicates_only' => 'Duplicates Only',
                    'flexibility_issues' => 'Flexibility Issues (2+ Unclassified)',
                ])
                ->default('all')
                ->reactive()
                ->afterStateUpdated(fn () => $this->updateAttendances()),
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
            return collect();
        }

        $payPeriod = PayPeriod::find($this->payPeriodId);
        if (!$payPeriod) {
            $this->duplicatesFilter = 'all';
            return collect();
        }

        Log::info("[AttendanceSummary] Fetching attendance records for PayPeriod ID: {$this->payPeriodId} ({$payPeriod->start_date} to {$payPeriod->end_date})");

        // Fetch all attendance records within the date range
        $attendances = Attendance::with('employee')
            ->select([
                'attendances.id as attendance_id',
                'attendances.employee_id',
                'attendances.device_id',
                'attendances.shift_date',
                'attendances.punch_time',
                'attendances.punch_type_id',
                'attendances.punch_state',
                'employees.full_names as FullName',
                'employees.external_id as PayrollID',
                DB::raw("ANY_VALUE(attendances.status) as status"),
            ])
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->whereBetween('attendances.shift_date', [$payPeriod->start_date, $payPeriod->end_date])
            ->when($this->statusFilter !== 'all', function ($q) {
                if ($this->statusFilter === 'problem') {
                    $q->where('attendances.status', '!=', 'Migrated');
                } else {
                    $q->where('attendances.status', $this->statusFilter);
                }
            })
            ->orderBy('attendances.employee_id')
            ->orderBy('attendances.shift_date')
            ->orderBy('attendances.punch_time')
            ->when($this->search, function ($query) {
                $searchTerm = '%' . $this->search . '%';

                $query->where(function ($q) use ($searchTerm) {
                    $q->where('employees.full_names', 'like', $searchTerm)
                      ->orWhere('employees.external_id', 'like', $searchTerm)
                      ->orWhere('attendances.punch_state', 'like', $searchTerm)
                      ->orWhere(DB::raw("DATE_FORMAT(attendances.shift_date, '%Y-%m-%d')"), 'like', $searchTerm)
                      ->orWhere(DB::raw("DATE_FORMAT(attendances.punch_time, '%H:%i')"), 'like', $searchTerm);
                });
            })
            ->get();

        if ($this->duplicatesFilter === 'duplicates_only') {
            $duplicateKeys = $attendances
                ->groupBy(fn ($p) => "{$p->employee_id}|{$p->shift_date}|{$p->punch_type_id}")
                ->filter(fn ($group) => $group->count() > 1)
                ->keys();

            $attendances = $attendances->filter(function ($item) use ($duplicateKeys) {
                return $duplicateKeys->contains("{$item->employee_id}|{$item->shift_date}|{$item->punch_type_id}");
            })->values();
        }

        Log::info("[AttendanceSummary] Retrieved {$attendances->count()} attendance records.");

        // Identify employees with duplicate punches for the same punch_type_id on the same shift_date
        $duplicates = $attendances
            ->groupBy(fn ($p) => "{$p->employee_id}|{$p->shift_date}|{$p->punch_type_id}")
            ->filter(fn ($punches) => $punches->count() > 1)
            ->mapWithKeys(fn ($punches, $key) => [$key => $punches->pluck('attendance_id')->toArray()]);

        if ($duplicates->isNotEmpty()) {
            Log::info("[AttendanceSummary] Duplicate punches detected:", $duplicates->toArray());
        } else {
            Log::info("[AttendanceSummary] No duplicates found.");
        }

        // Group data per employee and shift date
        $grouped = $attendances->groupBy(function ($attendance) {
            return "{$attendance->employee_id}|{$attendance->shift_date}";
        })->map(function ($punchesPerDay) use ($duplicates) {
            $punchesSorted = [
                'start_time' => [],
                'lunch_start' => [],
                'lunch_stop' => [],
                'stop_time' => [],
                'unclassified' => [],
            ];

            foreach ($punchesPerDay as $punch) {
                $type = match ($punch->punch_type_id) {
                    1 => 'start_time',
                    2 => 'stop_time',
                    3 => 'lunch_start',
                    4 => 'lunch_stop',
                    default => 'unclassified'
                };

                $key = "{$punch->employee_id}|{$punch->shift_date}|{$punch->punch_type_id}";
                $hasMultiple = isset($duplicates[$key]);

                $entry = [
                    'attendance_id' => $punch->attendance_id,
                    'punch_time' => \Carbon\Carbon::parse($punch->punch_time)->format('H:i:s'),
                    'punch_state' => $punch->punch_state,
                    'device_id' => $punch->device_id,
                    'punch_type' => $type,
                    'multiple' => $hasMultiple,
                ];

                if ($hasMultiple) {
                    $entry['multiples_list'] = $punchesPerDay->filter(function ($other) use ($punch) {
                        return $other->punch_type_id === $punch->punch_type_id
                            && $other->shift_date === $punch->shift_date
                            && $other->employee_id === $punch->employee_id;
                    })->map(function ($dup) use ($type) {
                        return collect([
                            'attendance_id' => (string) $dup->attendance_id,
                            'punch_time' => (string) \Carbon\Carbon::parse($dup->punch_time)->format('H:i:s'),
                            'punch_state' => (string) $dup->punch_state,
                            'device_id' => (string) $dup->device_id,
                            'punch_type' => (string) $type,
                        ])->all();
                    })->values()->toArray();
                }

                $punchesSorted[$type][] = $entry;
            }

            // Check for flexibility issues (multiple unclassified punches indicating timing problems)
            $unclassifiedCount = count($punchesSorted['unclassified']);
            $hasFlexibilityIssue = $unclassifiedCount >= 2;
            
            return [
                'employee' => [
                    'employee_id' => $punchesPerDay->first()->employee_id,
                    'FullName' => $punchesPerDay->first()->FullName,
                    'PayrollID' => $punchesPerDay->first()->PayrollID,
                    'shift_date' => $punchesPerDay->first()->shift_date,
                    'status' => $punchesPerDay->first()->status,
                    'has_flexibility_issue' => $hasFlexibilityIssue,
                ],
                'punches' => $punchesSorted,
            ];
        });

        // Apply flexibility issues filter
        if ($this->duplicatesFilter === 'flexibility_issues') {
            $grouped = $grouped->filter(function ($item) {
                return $item['employee']['has_flexibility_issue'] ?? false;
            });
        }

        return $grouped->values();
    }

    public function updateAttendances(): void
    {
        Log::info("[AttendanceSummary] updateAttendances() called.");
        $this->groupedAttendances = $this->fetchAttendances();
        $this->dispatch('$refresh');
        Log::info("[AttendanceSummary] updateAttendances() completed.");
    }

    public function sortBy($field): void
    {
        $columnMap = [
            'FullName' => 'employees.full_names',
            'shift_date' => 'shift_date',
            'start_time' => 'start_time',
            'lunch_start' => 'lunch_start',
            'lunch_stop' => 'lunch_stop',
            'stop_time' => 'stop_time',
        ];

        if (!isset($columnMap[$field])) {
            return;
        }

        $this->sortDirection = ($this->sortColumn === $field && $this->sortDirection === 'asc') ? 'desc' : 'asc';
        $this->sortColumn = $columnMap[$field];
        $this->updateAttendances();
    }

    protected function getActions(): array
    {
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
        'open-update-modal' => 'openUpdateModal',
        'open-create-modal' => 'openCreateModal',
    ];

    public function refreshAttendanceData(): void
    {
        $this->updateAttendances();
    }
}
