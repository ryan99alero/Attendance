<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayPeriod;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;

class C_AttendanceSummary extends Page implements HasTable
{
    use InteractsWithTable, InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Attendance Summary (Claude)';
    protected static ?string $title = 'Attendance Summary - Claude Version';
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.c-attendance-summary';

    // Form state - using Filament v4 state management
    public ?array $data = [];

    // Table data cache
    protected ?Collection $cachedAttendanceData = null;

    public function mount(): void
    {
        // Initialize form with sensible defaults
        $this->form->fill([
            'pay_period_id' => null,
            'status_filter' => 'problem',
            'duplicates_filter' => 'all',
            'search_term' => '',
            'date_range_start' => null,
            'date_range_end' => null,
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('pay_period_id')
                ->label('Pay Period')
                ->placeholder('All Pay Periods')
                ->options($this->getPayPeriodOptions())
                ->live()
                ->afterStateUpdated(fn () => $this->resetTableCache()),

            Select::make('status_filter')
                ->label('Status Filter')
                ->options([
                    'all' => 'All Records',
                    'problem' => 'Problem Records',
                    'Migrated' => 'Migrated Only',
                    'NeedsReview' => 'Needs Review',
                ])
                ->default('problem')
                ->live()
                ->afterStateUpdated(fn () => $this->resetTableCache()),

            Select::make('duplicates_filter')
                ->label('Duplicates')
                ->options([
                    'all' => 'All Records',
                    'duplicates_only' => 'Duplicates Only',
                    'no_duplicates' => 'No Duplicates',
                ])
                ->default('all')
                ->live()
                ->afterStateUpdated(fn () => $this->resetTableCache()),

            TextInput::make('search_term')
                ->label('Search')
                ->placeholder('Search employees, punch states, etc...')
                ->live()
                ->afterStateUpdated(fn () => $this->resetTableCache()),

            Select::make('employee_id')
                ->label('Specific Employee')
                ->placeholder('All Employees')
                ->options(Employee::query()->pluck('full_names', 'id'))
                ->live()
                ->afterStateUpdated(fn () => $this->resetTableCache()),

            DatePicker::make('date_range_start')
                ->label('Date Range Start')
                ->live()
                ->afterStateUpdated(fn () => $this->resetTableCache()),

            DatePicker::make('date_range_end')
                ->label('Date Range End')
                ->live()
                ->afterStateUpdated(fn () => $this->resetTableCache()),
        ];
    }

    #[Computed]
    public function getPayPeriodOptions(): array
    {
        return PayPeriod::query()
            ->orderBy('start_date', 'desc')
            ->get()
            ->mapWithKeys(fn (PayPeriod $period) => [
                $period->id => "{$period->start_date->format('M j')} - {$period->end_date->format('M j, Y')}",
            ])
            ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('employee.full_names')
                    ->label('Employee')
                    ->searchable()
                    ->weight('medium')
                    ->icon('heroicon-m-user'),

                TextColumn::make('employee.external_id')
                    ->label('Payroll ID')
                    ->searchable(),

                TextColumn::make('shift_date')
                    ->label('Date')
                    ->date(),

                TextColumn::make('punch_times.start_time')
                    ->label('Clock In')
                    ->formatStateUsing(function ($record) {
                        return $this->getPunchTimesForRecord($record)['start_time'] ?? '-';
                    })
                    ->color(fn ($record) => $this->hasMultiplePunches($record, 1) ? 'danger' : 'success'),

                TextColumn::make('punch_times.lunch_start')
                    ->label('Lunch Start')
                    ->formatStateUsing(function ($record) {
                        return $this->getPunchTimesForRecord($record)['lunch_start'] ?? '-';
                    })
                    ->color(fn ($record) => $this->hasMultiplePunches($record, 3) ? 'danger' : 'success'),

                TextColumn::make('punch_times.lunch_stop')
                    ->label('Lunch Stop')
                    ->formatStateUsing(function ($record) {
                        return $this->getPunchTimesForRecord($record)['lunch_stop'] ?? '-';
                    })
                    ->color(fn ($record) => $this->hasMultiplePunches($record, 4) ? 'danger' : 'success'),

                TextColumn::make('punch_times.stop_time')
                    ->label('Clock Out')
                    ->formatStateUsing(function ($record) {
                        return $this->getPunchTimesForRecord($record)['stop_time'] ?? '-';
                    })
                    ->color(fn ($record) => $this->hasMultiplePunches($record, 2) ? 'danger' : 'success'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Migrated' => 'success',
                        'NeedsReview' => 'warning',
                        'Problem' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('total_hours')
                    ->label('Total Hours')
                    ->formatStateUsing(function ($record) {
                        return $this->calculateTotalHours($record);
                    })
                    ->numeric(2),
            ])
            ->filters([
                Filter::make('has_problems')
                    ->label('Has Problems')
                    ->query(fn (Builder $query): Builder =>
                        $query->where('status', '!=', 'Migrated')
                    ),

                Filter::make('has_duplicates')
                    ->label('Has Duplicates')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereIn('id', $this->getDuplicateAttendanceIds())
                    ),

                SelectFilter::make('status')
                    ->options([
                        'Migrated' => 'Migrated',
                        'NeedsReview' => 'Needs Review',
                        'Problem' => 'Problem',
                    ]),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')
                            ->label('From Date'),
                        DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('shift_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('shift_date', '<=', $date),
                            );
                    }),
            ])
            ->reorderable(false)
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->deferLoading();
    }

    protected function getTableQuery(): Builder
    {
        // Simplify - just use a basic Eloquent query without GROUP BY
        $query = Attendance::query()
            ->with(['employee'])
            ->select([
                'attendances.id',
                'attendances.employee_id', 
                'attendances.shift_date',
                'attendances.status',
            ])
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->orderBy('attendances.shift_date', 'desc');

        // Apply filters from form
        $filters = $this->data;

        if (!empty($filters['pay_period_id'])) {
            $payPeriod = PayPeriod::find($filters['pay_period_id']);
            if ($payPeriod) {
                $query->whereBetween('attendances.shift_date', [$payPeriod->start_date, $payPeriod->end_date]);
            }
        }

        if (!empty($filters['date_range_start'])) {
            $query->whereDate('attendances.shift_date', '>=', $filters['date_range_start']);
        }

        if (!empty($filters['date_range_end'])) {
            $query->whereDate('attendances.shift_date', '<=', $filters['date_range_end']);
        }

        if (!empty($filters['employee_id'])) {
            $query->where('attendances.employee_id', $filters['employee_id']);
        }

        if (!empty($filters['status_filter']) && $filters['status_filter'] !== 'all') {
            if ($filters['status_filter'] === 'problem') {
                $query->where('attendances.status', '!=', 'Migrated');
            } else {
                $query->where('attendances.status', $filters['status_filter']);
            }
        }

        if (!empty($filters['search_term'])) {
            $searchTerm = '%' . $filters['search_term'] . '%';
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('employees.full_names', 'like', $searchTerm)
                  ->orWhere('employees.external_id', 'like', $searchTerm)
                  ->orWhere('attendances.punch_state', 'like', $searchTerm);
            });
        }

        // Handle duplicates filter
        if (!empty($filters['duplicates_filter']) && $filters['duplicates_filter'] !== 'all') {
            $duplicateIds = $this->getDuplicateAttendanceIds();

            if ($filters['duplicates_filter'] === 'duplicates_only') {
                $query->whereIn('attendances.id', $duplicateIds);
            } elseif ($filters['duplicates_filter'] === 'no_duplicates') {
                $query->whereNotIn('attendances.id', $duplicateIds);
            }
        }

        return $query;
    }

    protected function resetTableCache(): void
    {
        $this->cachedAttendanceData = null;
    }

    protected function getPunchTimesForRecord($record): array
    {
        $punches = Attendance::where('employee_id', $record->employee_id)
            ->where('shift_date', $record->shift_date)
            ->get();

        $times = [
            'start_time' => null,
            'lunch_start' => null,
            'lunch_stop' => null,
            'stop_time' => null,
        ];

        foreach ($punches as $punch) {
            $time = Carbon::parse($punch->punch_time)->format('H:i');

            match ($punch->punch_type_id) {
                1 => $times['start_time'] = $times['start_time'] ? $times['start_time'] . ' (DUP)' : $time,
                2 => $times['stop_time'] = $times['stop_time'] ? $times['stop_time'] . ' (DUP)' : $time,
                3 => $times['lunch_start'] = $times['lunch_start'] ? $times['lunch_start'] . ' (DUP)' : $time,
                4 => $times['lunch_stop'] = $times['lunch_stop'] ? $times['lunch_stop'] . ' (DUP)' : $time,
                default => null,
            };
        }

        return $times;
    }

    protected function hasMultiplePunches($record, int $punchTypeId): bool
    {
        return Attendance::where('employee_id', $record->employee_id)
            ->where('shift_date', $record->shift_date)
            ->where('punch_type_id', $punchTypeId)
            ->count() > 1;
    }

    protected function getDuplicateAttendanceIds(): array
    {
        return Attendance::query()
            ->select('id')
            ->whereIn(
                DB::raw('CONCAT(employee_id, "|", shift_date, "|", punch_type_id)'),
                function ($query) {
                    $query->select(DB::raw('CONCAT(employee_id, "|", shift_date, "|", punch_type_id)'))
                        ->from('attendances')
                        ->groupBy(['employee_id', 'shift_date', 'punch_type_id'])
                        ->havingRaw('COUNT(*) > 1');
                }
            )
            ->pluck('id')
            ->toArray();
    }

    protected function getDuplicatesForRecord($record): Collection
    {
        return Attendance::where('employee_id', $record->employee_id)
            ->where('shift_date', $record->shift_date)
            ->get()
            ->groupBy('punch_type_id')
            ->filter(fn ($group) => $group->count() > 1);
    }

    protected function calculateTotalHours($record): string
    {
        $times = $this->getPunchTimesForRecord($record);

        if (!$times['start_time'] || !$times['stop_time']) {
            return '-';
        }

        try {
            $start = Carbon::parse($record->shift_date . ' ' . $times['start_time']);
            $end = Carbon::parse($record->shift_date . ' ' . $times['stop_time']);

            // Handle overnight shifts
            if ($end->lt($start)) {
                $end->addDay();
            }

            $totalMinutes = $end->diffInMinutes($start);

            // Subtract lunch time if both lunch punches exist
            if ($times['lunch_start'] && $times['lunch_stop']) {
                $lunchStart = Carbon::parse($record->shift_date . ' ' . $times['lunch_start']);
                $lunchEnd = Carbon::parse($record->shift_date . ' ' . $times['lunch_stop']);

                if ($lunchEnd->gt($lunchStart)) {
                    $totalMinutes -= $lunchEnd->diffInMinutes($lunchStart);
                }
            }

            return number_format($totalMinutes / 60, 2);
        } catch (\Exception $e) {
            return '-';
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('refresh')
                    ->label('Refresh Data')
                    ->icon('heroicon-m-arrow-path')
                    ->color('gray')
                    ->action(fn () => $this->resetTableCache()),

                Action::make('export_all')
                    ->label('Export All')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('primary')
                    ->action(function () {
                        Notification::make()
                            ->title('Export started')
                            ->body('Your export will be available shortly.')
                            ->info()
                            ->send();
                    }),

                Action::make('bulk_process')
                    ->label('Bulk Process')
                    ->icon('heroicon-m-cog-6-tooth')
                    ->color('warning')
                    ->modalHeading('Bulk Process Attendance')
                    ->modalDescription('Process multiple attendance records at once')
                    ->action(function () {
                        Notification::make()
                            ->title('Bulk processing started')
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Actions')
                ->icon('heroicon-m-ellipsis-vertical')
                ->button(),
        ];
    }

    public function getTitle(): string
    {
        return static::$title;
    }

    // Additional methods for enhanced functionality
    public function markAsMigrated(int $employeeId, string $shiftDate): void
    {
        Attendance::where('employee_id', $employeeId)
            ->where('shift_date', $shiftDate)
            ->update(['status' => 'Migrated']);

        Notification::make()
            ->title('Record marked as migrated')
            ->success()
            ->send();

        $this->resetTableCache();
    }

    public function deletePunch(int $punchId): void
    {
        $punch = Attendance::find($punchId);

        if ($punch) {
            $punch->delete();

            Notification::make()
                ->title('Punch deleted successfully')
                ->success()
                ->send();

            $this->resetTableCache();
        }
    }

    public function openEditModal(int $employeeId, string $shiftDate): void
    {
        $this->dispatch('open-edit-punch-modal', [
            'employeeId' => $employeeId,
            'shiftDate' => $shiftDate,
        ]);
    }

    // Livewire event listeners for enhanced interactions
    protected $listeners = [
        'refreshData' => 'resetTableCache',
        'punchUpdated' => 'resetTableCache',
        'punchCreated' => 'resetTableCache',
    ];

    // Helper method to get form data safely
    protected function getFormData(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    // Override table sorting to prevent automatic ID sorting
    public function isTableSortable(): bool
    {
        return false;
    }

    // Override the table query builder to prevent automatic ordering
    protected function applyTableSortingToTableQuery(Builder $query): Builder
    {
        // Return query without any additional sorting to prevent ID ordering
        return $query;
    }
}
