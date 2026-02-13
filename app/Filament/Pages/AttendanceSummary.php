<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Models\PayPeriod;
use App\Models\PunchType;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;

class AttendanceSummary extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table';

    protected string $view = 'filament.pages.attendance-summary';

    protected static ?string $navigationLabel = 'Attendance Summary';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public $groupedAttendances;

    public array $selectedAttendances = [];

    public bool $selectAll = false;

    public bool $autoProcess = false;

    public $sortColumn = 'shift_date';

    public $sortDirection = 'asc';

    public function mount(): void
    {
        Log::info('[AttendanceSummary] mount() called - Initializing component.');

        $this->groupedAttendances = collect();

        // Initialize data array with URL parameters
        $this->data = [
            'payPeriodId' => request()->get('payPeriodId'),
            'search' => '',
            'statusFilter' => request()->get('statusFilter', 'problem'),
            'duplicatesFilter' => request()->get('duplicatesFilter', 'all'),
        ];

        // Fill the form with initial data
        $this->form->fill($this->data);

        // If payPeriodId was set from URL, fetch attendances
        if (request()->has('payPeriodId')) {
            $this->groupedAttendances = $this->fetchAttendances();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Select::make('payPeriodId')
                    ->label('Select Pay Period')
                    ->options($this->getPayPeriods())
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateAttendances())
                    ->placeholder('All Pay Periods'),

                TextInput::make('search')
                    ->label('Search')
                    ->placeholder('Search any value...')
                    ->live(debounce: 500)
                    ->afterStateUpdated(fn () => $this->updateAttendances()),

                Select::make('statusFilter')
                    ->label('Filter by Status')
                    ->options([
                        'all' => 'All',
                        'Migrated' => 'Migrated',
                        'NeedsReview' => 'Needs Review',
                        'problem' => 'Problem (All Except Migrated)',
                        'problem_with_migrated' => 'Problem (Including Migrated)',
                    ])
                    ->default('problem')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateAttendances()),

                Select::make('duplicatesFilter')
                    ->label('Filter by Issues')
                    ->options([
                        'all' => 'All',
                        'duplicates_only' => 'Duplicates Only',
                        'flexibility_issues' => 'Flexibility Issues (2+ Unclassified)',
                        'consensus' => 'Engine Discrepancy',
                    ])
                    ->default('all')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateAttendances()),
            ])
            ->statePath('data');
    }

    protected function getPayPeriods(): array
    {
        return PayPeriod::query()
            ->select('id', 'start_date', 'end_date')
            ->get()
            ->mapWithKeys(fn ($period) => [
                $period->id => $period->start_date.' to '.$period->end_date,
            ])
            ->toArray();
    }

    public function getPunchTypes(): Collection
    {
        return PunchType::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    public function getPunchTypeMapping(): array
    {
        return $this->getPunchTypes()
            ->mapWithKeys(function ($punchType) {
                $key = strtolower(str_replace(' ', '_', $punchType->name));

                return [$punchType->id => $key];
            })
            ->toArray();
    }

    public function getPunchTypeColumns(): array
    {
        $columns = $this->getPunchTypes()
            ->mapWithKeys(function ($punchType) {
                $key = strtolower(str_replace(' ', '_', $punchType->name));

                return [$key => $punchType->name];
            })
            ->toArray();

        $columns['unclassified'] = 'Unclassified';

        return $columns;
    }

    public function getVisibleColumns(): array
    {
        // Core columns that should always be shown
        $coreColumns = [
            'clock_in' => 'Clock In',
            'lunch_start' => 'Lunch Start',
            'lunch_stop' => 'Lunch Stop',
            'clock_out' => 'Clock Out',
            'unclassified' => 'Unclassified',
        ];

        // If no data loaded yet, return just core columns
        if ($this->groupedAttendances->isEmpty()) {
            return $coreColumns;
        }

        // Find which punch types actually have data
        $columnsWithData = [];
        foreach ($this->groupedAttendances as $attendance) {
            foreach ($attendance['punches'] as $punchType => $punches) {
                if (! empty($punches)) {
                    $columnsWithData[$punchType] = true;
                }
            }
        }

        // Start with core columns
        $visibleColumns = $coreColumns;

        // Add any additional columns that have data but aren't in core
        $allColumns = $this->getPunchTypeColumns();
        foreach (array_keys($columnsWithData) as $punchType) {
            if (! isset($coreColumns[$punchType]) && isset($allColumns[$punchType])) {
                $visibleColumns[$punchType] = $allColumns[$punchType];
            }
        }

        return $visibleColumns;
    }

    public function fetchAttendances(): Collection
    {
        $payPeriodId = $this->data['payPeriodId'] ?? null;
        $statusFilter = $this->data['statusFilter'] ?? 'NeedsReview';
        $duplicatesFilter = $this->data['duplicatesFilter'] ?? 'all';
        $search = $this->data['search'] ?? '';

        if (! $payPeriodId) {
            return collect();
        }

        $payPeriod = PayPeriod::find($payPeriodId);
        if (! $payPeriod) {
            $this->data['duplicatesFilter'] = 'all';

            return collect();
        }

        Log::info("[AttendanceSummary] Fetching attendance records for PayPeriod ID: {$payPeriodId} ({$payPeriod->start_date} to {$payPeriod->end_date})");

        // Fetch all attendance records within the date range
        $attendancesQuery = Attendance::with('employee')
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
                DB::raw('ANY_VALUE(attendances.status) as status'),
            ])
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->whereBetween('attendances.shift_date', [$payPeriod->start_date, $payPeriod->end_date]);

        // Apply department filtering for managers
        $user = auth()->user();
        if ($user && $user->hasRole('manager') && ! $user->hasRole('super_admin')) {
            $managedEmployeeIds = $user->getManagedEmployeeIds();

            if (! empty($managedEmployeeIds)) {
                $attendancesQuery->whereIn('attendances.employee_id', $managedEmployeeIds);
            } else {
                // If manager has no employees, show no records
                $attendancesQuery->whereRaw('1 = 0');
            }
        }

        // Handle different status filters
        // Issue criteria matches PayPeriod::attendanceIssues():
        // - status = 'NeedsReview'
        // - OR status = 'Incomplete' with punch_type_id assigned
        if ($statusFilter === 'problem' || $statusFilter === 'problem_with_migrated') {
            // Find employee/date combinations that have actual issue records
            $problemDays = Attendance::select('employee_id', 'shift_date')
                ->whereBetween('shift_date', [$payPeriod->start_date, $payPeriod->end_date])
                ->where(function ($query) {
                    $query->where('status', 'NeedsReview')
                        ->orWhere(function ($subQuery) {
                            $subQuery->where('status', 'Incomplete')
                                ->whereNotNull('punch_type_id');
                        });
                })
                ->get()
                ->map(fn ($record) => $record->employee_id.'|'.$record->shift_date)
                ->unique()
                ->values();

            if ($problemDays->isNotEmpty()) {
                // Get all records for those employee/date combinations
                $attendancesQuery->where(function ($query) use ($problemDays) {
                    foreach ($problemDays as $day) {
                        [$employeeId, $shiftDate] = explode('|', $day);
                        $query->orWhere(function ($subQuery) use ($employeeId, $shiftDate) {
                            $subQuery->where('attendances.employee_id', $employeeId)
                                ->where('attendances.shift_date', $shiftDate);
                        });
                    }
                });

                // For 'problem' filter, exclude Migrated records from the results
                if ($statusFilter === 'problem') {
                    $attendancesQuery->where('attendances.status', '!=', 'Migrated');
                }
                // For 'problem_with_migrated', show all records including Migrated
            } else {
                // No problem days found, return empty result
                $attendancesQuery->whereRaw('1 = 0');
            }
        } elseif ($statusFilter !== 'all') {
            $attendancesQuery->where('attendances.status', $statusFilter);
        }

        $attendances = $attendancesQuery
            ->orderBy('attendances.employee_id')
            ->orderBy('attendances.shift_date')
            ->orderBy('attendances.punch_time')
            ->when($search, function ($query) use ($search) {
                $searchTerm = '%'.$search.'%';

                $query->where(function ($q) use ($searchTerm) {
                    $q->where('employees.full_names', 'like', $searchTerm)
                        ->orWhere('employees.external_id', 'like', $searchTerm)
                        ->orWhere('attendances.punch_state', 'like', $searchTerm)
                        ->orWhere(DB::raw("DATE_FORMAT(attendances.shift_date, '%Y-%m-%d')"), 'like', $searchTerm)
                        ->orWhere(DB::raw("DATE_FORMAT(attendances.punch_time, '%H:%i')"), 'like', $searchTerm);
                });
            })
            ->get();

        if ($duplicatesFilter === 'duplicates_only') {
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
            Log::info('[AttendanceSummary] Duplicate punches detected:', $duplicates->toArray());
        } else {
            Log::info('[AttendanceSummary] No duplicates found.');
        }

        // Get dynamic punch type mapping
        $punchTypeMapping = $this->getPunchTypeMapping();
        $punchTypeColumns = $this->getPunchTypeColumns();

        // Group data per employee and shift date
        $grouped = $attendances->groupBy(function ($attendance) {
            return "{$attendance->employee_id}|{$attendance->shift_date}";
        })->map(function ($punchesPerDay) use ($duplicates, $punchTypeMapping, $punchTypeColumns) {
            // Initialize array with all punch type columns
            $punchesSorted = array_fill_keys(array_keys($punchTypeColumns), []);

            foreach ($punchesPerDay as $punch) {
                $type = $punchTypeMapping[$punch->punch_type_id] ?? 'unclassified';

                $key = "{$punch->employee_id}|{$punch->shift_date}|{$punch->punch_type_id}";
                $hasMultiple = isset($duplicates[$key]);

                $entry = [
                    'attendance_id' => $punch->attendance_id,
                    'punch_time' => Carbon::parse($punch->punch_time)->format('H:i:s'),
                    'punch_state' => $punch->punch_state,
                    'device_id' => $punch->device_id,
                    'punch_type' => $type,
                    'multiple' => $hasMultiple,
                    'status' => $punch->status, // Add individual punch status
                ];

                if ($hasMultiple) {
                    $entry['multiples_list'] = $punchesPerDay->filter(function ($other) use ($punch) {
                        return $other->punch_type_id === $punch->punch_type_id
                            && $other->shift_date === $punch->shift_date
                            && $other->employee_id === $punch->employee_id;
                    })->map(function ($dup) use ($type) {
                        return collect([
                            'attendance_id' => (string) $dup->attendance_id,
                            'punch_time' => (string) Carbon::parse($dup->punch_time)->format('H:i:s'),
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
        if ($duplicatesFilter === 'flexibility_issues') {
            $grouped = $grouped->filter(function ($item) {
                return $item['employee']['has_flexibility_issue'] ?? false;
            });
        }

        // Apply consensus filter
        if ($duplicatesFilter === 'consensus') {
            $grouped = $grouped->filter(function ($item) {
                return $item['employee']['status'] === 'Discrepancy';
            });
        }

        return $grouped->values();
    }

    public function updateAttendances(): void
    {
        // Sync form state to data array
        $this->data = $this->form->getState();

        Log::info('[AttendanceSummary] updateAttendances() called with filters:', [
            'payPeriodId' => $this->data['payPeriodId'] ?? null,
            'statusFilter' => $this->data['statusFilter'] ?? null,
            'duplicatesFilter' => $this->data['duplicatesFilter'] ?? null,
        ]);

        $this->groupedAttendances = $this->fetchAttendances();
        Log::info('[AttendanceSummary] updateAttendances() completed. Records: '.$this->groupedAttendances->count());
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

        if (! isset($columnMap[$field])) {
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

    #[On('processSelected')]
    public function processSelected(): void
    {
        // Process selected records
    }

    #[On('timeRecordCreated')]
    #[On('timeRecordUpdated')]
    public function refreshAttendanceData(): void
    {
        $this->updateAttendances();
    }
}
