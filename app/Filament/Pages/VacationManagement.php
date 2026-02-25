<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Models\Employee;
use App\Models\VacationBalance;
use App\Models\VacationCalendar;
use App\Models\VacationPolicy;
use App\Models\VacationRequest;
use App\Models\VacationTransaction;
use App\Services\VacationRequestService;
use Carbon\Carbon;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class VacationManagement extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Time Off Management';

    protected static ?string $navigationLabel = 'Vacation Management';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.vacation-management';

    public string $activeTab = 'requests';

    public ?array $processingData = [];

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(['super_admin', 'admin', 'manager']) ?? false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(['super_admin', 'admin', 'manager']) ?? false;
    }

    public function mount(): void
    {
        $this->activeTab = request('tab', 'requests');
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return match ($this->activeTab) {
            'calendar' => $this->getCalendarTable($table),
            'balances' => $this->getBalancesTable($table),
            'policies' => $this->getPoliciesTable($table),
            'processing' => $this->getProcessingTable($table),
            default => $this->getRequestsTable($table),
        };
    }

    protected function getRequestsTable(Table $table): Table
    {
        $user = Auth::user();
        $isAdmin = $user?->hasRole(['super_admin', 'admin']);

        return $table
            ->query(function () use ($user, $isAdmin) {
                $query = VacationRequest::query()->with(['employee.department', 'reviewer']);

                if (! $isAdmin && $user?->hasRole('manager')) {
                    $employee = Employee::where('email', $user->email)->first();
                    if ($employee) {
                        $query->forManager($employee->id);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }

                return $query;
            })
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('employee', function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('employee.department.name')
                    ->label('Department'),

                TextColumn::make('date_range')
                    ->label('Dates')
                    ->getStateUsing(fn (VacationRequest $record) => $record->date_range),

                TextColumn::make('hours_requested')
                    ->label('Hours')
                    ->numeric(decimalPlaces: 1)
                    ->suffix(' hrs')
                    ->sortable(),

                IconColumn::make('is_half_day')
                    ->label('Half Day')
                    ->boolean(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'denied' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->placeholder('Pending')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'denied' => 'Denied',
                    ]),

                SelectFilter::make('employee.department_id')
                    ->label('Department')
                    ->relationship('employee.department', 'name')
                    ->visible(fn () => Auth::user()?->hasRole(['super_admin', 'admin'])),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes (Optional)')
                            ->rows(2),
                    ])
                    ->action(function (VacationRequest $record, array $data): void {
                        $service = app(VacationRequestService::class);
                        $service->approveRequest($record, Auth::user(), $data['notes'] ?? null);

                        Notification::make()
                            ->title('Request Approved')
                            ->body("Vacation request for {$record->employee->full_name} has been approved.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (VacationRequest $record) => $record->isPending()),

                Action::make('deny')
                    ->label('Deny')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (VacationRequest $record, array $data): void {
                        $service = app(VacationRequestService::class);
                        $service->denyRequest($record, Auth::user(), $data['reason']);

                        Notification::make()
                            ->title('Request Denied')
                            ->body("Vacation request for {$record->employee->full_name} has been denied.")
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (VacationRequest $record) => $record->isPending()),

                Action::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (VacationRequest $record) => 'Time Off Request')
                    ->modalWidth('lg')
                    ->infolist([
                        Section::make('Employee Information')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('employee.full_name')
                                            ->label('Employee')
                                            ->weight('bold'),
                                        TextEntry::make('employee.department.name')
                                            ->label('Department')
                                            ->icon('heroicon-o-building-office'),
                                    ]),
                            ])
                            ->columnSpanFull(),

                        Section::make('Request Details')
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('date_range')
                                            ->label('Date Range')
                                            ->icon('heroicon-o-calendar')
                                            ->weight('bold'),
                                        TextEntry::make('hours_requested')
                                            ->label('Hours Requested')
                                            ->icon('heroicon-o-clock')
                                            ->suffix(' hours')
                                            ->color('primary'),
                                        TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'denied' => 'danger',
                                                default => 'gray',
                                            }),
                                    ]),
                                IconEntry::make('is_half_day')
                                    ->label('Half Day Request')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('gray'),
                                TextEntry::make('notes')
                                    ->label('Employee Notes')
                                    ->placeholder('No notes provided')
                                    ->markdown()
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        Section::make('Review Information')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('reviewer.name')
                                            ->label('Reviewed By')
                                            ->icon('heroicon-o-user-circle')
                                            ->placeholder('Pending review'),
                                        TextEntry::make('reviewed_at')
                                            ->label('Reviewed At')
                                            ->icon('heroicon-o-clock')
                                            ->dateTime()
                                            ->placeholder('Not yet reviewed'),
                                    ]),
                                TextEntry::make('review_notes')
                                    ->label('Review Notes')
                                    ->placeholder('No review notes')
                                    ->markdown()
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn (VacationRequest $record) => ! $record->isPending())
                            ->columnSpanFull(),

                        Section::make('Submission Information')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('creator.name')
                                            ->label('Submitted By')
                                            ->icon('heroicon-o-user')
                                            ->placeholder('Employee'),
                                        TextEntry::make('created_at')
                                            ->label('Submitted At')
                                            ->icon('heroicon-o-calendar')
                                            ->dateTime(),
                                    ]),
                            ])
                            ->collapsed()
                            ->columnSpanFull(),
                    ]),
            ])
            ->toolbarActions([
                BulkAction::make('bulkApprove')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $service = app(VacationRequestService::class);
                        $count = 0;

                        foreach ($records as $record) {
                            if ($record->isPending()) {
                                $service->approveRequest($record, Auth::user());
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title('Requests Approved')
                            ->body("{$count} vacation requests have been approved.")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    protected function getCalendarTable(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->query(function () use ($user) {
                $query = VacationCalendar::query()->with('employee.department');

                if ($user?->hasRole(['admin', 'super_admin'])) {
                    return $query;
                }

                if ($user?->hasRole('manager')) {
                    $employee = Employee::where('email', $user->email)->first();

                    if ($employee) {
                        $managedDepartmentIds = Department::where('manager_id', $employee->id)->pluck('id');

                        if ($managedDepartmentIds->isNotEmpty()) {
                            return $query->whereHas('employee', function (Builder $employeeQuery) use ($managedDepartmentIds) {
                                $employeeQuery->whereIn('department_id', $managedDepartmentIds);
                            });
                        }
                    }

                    return $query->whereRaw('1 = 0');
                }

                return $query->whereRaw('1 = 0');
            })
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('employee', function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('vacation_date')
                    ->label('Vacation Date')
                    ->date()
                    ->sortable(),

                IconColumn::make('is_half_day')
                    ->label('Half Day')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('vacation_date', 'desc')
            ->filters([
                SelectFilter::make('employee_id')
                    ->relationship('employee', 'first_name')
                    ->searchable()
                    ->preload(),
            ]);
    }

    protected function getBalancesTable(Table $table): Table
    {
        return $table
            ->query(VacationBalance::query()->with('employee'))
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('employee', function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('accrued_hours')
                    ->label('Accrued Hours')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' hrs')
                    ->sortable(),

                TextColumn::make('used_hours')
                    ->label('Used Hours')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' hrs')
                    ->sortable(),

                TextColumn::make('available_hours')
                    ->label('Available Hours')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' hrs')
                    ->getStateUsing(fn ($record) => $record->accrued_hours - $record->used_hours)
                    ->sortable()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),

                TextColumn::make('carry_over_hours')
                    ->label('Carry Over')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' hrs')
                    ->placeholder('None')
                    ->sortable(),

                TextColumn::make('cap_hours')
                    ->label('Cap')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' hrs')
                    ->sortable(),
            ]);
    }

    protected function getPoliciesTable(Table $table): Table
    {
        return $table
            ->query(VacationPolicy::query())
            ->columns([
                TextColumn::make('policy_name')
                    ->label('Policy Name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('min_tenure_years')
                    ->label('Min Years')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('max_tenure_years')
                    ->label('Max Years')
                    ->placeholder('No limit')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('vacation_days_per_year')
                    ->label('Days/Year')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('vacation_hours_per_year')
                    ->label('Hours/Year')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order');
    }

    protected function getProcessingTable(Table $table): Table
    {
        return $table
            ->query(
                VacationTransaction::query()
                    ->where('transaction_type', 'accrual')
                    ->with('employee')
            )
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('employee', function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('hours')
                    ->label('Hours Awarded')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' hrs')
                    ->sortable(),

                TextColumn::make('effective_date')
                    ->label('Anniversary Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('transaction_date')
                    ->label('Processed Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('accrual_period')
                    ->label('Period')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn (TextColumn $column): ?string => strlen($column->getState()) > 50 ? $column->getState() : null),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('processAccruals')
                ->label('Process Accruals')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->activeTab === 'processing')
                ->schema([
                    DatePicker::make('processDate')
                        ->label('Process Date')
                        ->default(Carbon::now())
                        ->required(),

                    Select::make('selectedEmployee')
                        ->label('Employee (Optional)')
                        ->placeholder('Process all eligible employees')
                        ->options(
                            Employee::where('is_active', true)
                                ->whereNotNull('date_of_hire')
                                ->orderBy('first_name')
                                ->pluck('full_names', 'id')
                        )
                        ->searchable(),

                    Toggle::make('dryRun')
                        ->label('Dry Run (Preview Only)')
                        ->default(true)
                        ->helperText('Enable to see what would be processed without making changes'),

                    Toggle::make('force')
                        ->label('Force Reprocessing')
                        ->default(false)
                        ->helperText('Reprocess even if already processed for this anniversary'),
                ])
                ->action(function (array $data) {
                    $this->processVacationAccruals($data);
                }),
        ];
    }

    public function processVacationAccruals(array $data): void
    {
        $command = 'vacation:process-accruals';
        $options = [];

        if ($data['processDate']) {
            $options['--date'] = Carbon::parse($data['processDate'])->toDateString();
        }

        if ($data['selectedEmployee']) {
            $options['--employee'] = $data['selectedEmployee'];
        }

        if ($data['dryRun']) {
            $options['--dry-run'] = true;
        }

        if ($data['force']) {
            $options['--force'] = true;
        }

        try {
            $exitCode = Artisan::call($command, $options);

            if ($exitCode === 0) {
                Notification::make()
                    ->title('Vacation Processing Completed')
                    ->body($data['dryRun'] ? 'Dry run completed successfully' : 'Vacation accruals processed successfully')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Processing Failed')
                    ->body('There were errors during processing. Check the logs for details.')
                    ->danger()
                    ->send();
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('Processing Error')
                ->body('Error: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getPendingRequestsCount(): int
    {
        $user = Auth::user();

        $query = VacationRequest::pending();

        if (! $user?->hasRole(['super_admin', 'admin'])) {
            $employee = Employee::where('email', $user?->email)->first();
            if ($employee) {
                $query->forManager($employee->id);
            } else {
                return 0;
            }
        }

        return $query->count();
    }
}
