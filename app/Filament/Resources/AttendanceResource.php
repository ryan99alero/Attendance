<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages\CreateAttendance;
use App\Filament\Resources\AttendanceResource\Pages\EditAttendance;
use App\Filament\Resources\AttendanceResource\Pages\ListAttendances;
use App\Models\Attendance;
use App\Models\Classification;
use App\Models\Employee;
use App\Models\PayPeriod;
use App\Models\PunchType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    // Navigation Configuration
    protected static string|\UnitEnum|null $navigationGroup = 'Time Tracking';

    protected static ?string $navigationLabel = 'Attendances';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static ?int $navigationSort = -100;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')
                ->label('Employee')
                ->options(Employee::orderBy('last_name')->orderBy('first_name')
                    ->get()
                    ->mapWithKeys(fn ($employee) => [$employee->id => "{$employee->last_name}, {$employee->first_name}"])
                    ->toArray())
                ->placeholder('Select an Employee')
                ->searchable()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $employee = Employee::find($state);
                        if ($employee) {
                            $set('employee_external_id', $employee->external_id);
                        }
                    }
                })
                ->required(),

            TextInput::make('employee_external_id')
                ->label('Employee External ID')
                ->placeholder('Enter External ID')
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $employee = Employee::where('external_id', $state)->first();
                        if ($employee) {
                            $set('employee_id', $employee->id);
                        }
                    }
                })
                ->nullable(),

            Select::make('device_id')
                ->relationship('device', 'device_name')
                ->label('Device')
                ->nullable(),

            DateTimePicker::make('punch_time')
                ->label('Punch Time')
                ->seconds(false)
                ->displayFormat('Y-m-d H:i:s')
                ->required(),

            Select::make('punch_type_id')
                ->label('Punch Type')
                ->options(PunchType::pluck('name', 'id'))
                ->nullable()
                ->searchable(),

            Select::make('classification_id')
                ->label('Classification')
                ->options(Classification::where('is_active', 1)->pluck('name', 'id'))
                ->default(function () {
                    return Classification::where('code', 'REGULAR')->value('id');
                })
                ->nullable()
                ->searchable()
                ->placeholder('Select Classification (e.g., Vacation, Holiday)'),

            Toggle::make('is_manual')
                ->label('Manually Recorded')
                ->default(true),

            Select::make('status')
                ->label('Status')
                ->options(fn () => Attendance::getStatusOptions())
                ->default('Incomplete')
                ->placeholder('Select a Status')
                ->required(),

            Textarea::make('issue_notes')
                ->label('Issue Notes')
                ->rows(3)
                ->placeholder('Enter details about any issues or anomalies'),

            Toggle::make('is_migrated')
                ->label('Punch Recorded')
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $filter = request()->input('filter');

                // Hide archived records by default unless show_archived filter is enabled
                $tableFilters = request()->input('tableFilters', []);
                $showArchived = $tableFilters['show_archived']['value'] ?? false;
                if (! $showArchived) {
                    $query->where(function ($q) {
                        $q->where('is_archived', false)->orWhereNull('is_archived');
                    });
                }

                // Handle `filter_ids` if passed
                $filterIds = request()->input('filter_ids');
                if ($filterIds) {
                    $ids = array_filter(explode(',', $filterIds), 'is_numeric');
                    $query->whereIn('id', $ids);
                }

                // Apply date range filter
                if ($filter && isset($filter['date_range']['start'], $filter['date_range']['end'])) {
                    $query->whereBetween('punch_time', [
                        $filter['date_range']['start'],
                        $filter['date_range']['end'],
                    ]);
                }

                // Apply consensus review filter - show all records from days that have Discrepancy records
                if ($filter && isset($filter['consensus_review']) && $filter['consensus_review']) {
                    // Get all dates that have Discrepancy records within the date range
                    $dateRangeCondition = function ($subQuery) use ($filter) {
                        if (isset($filter['date_range']['start'], $filter['date_range']['end'])) {
                            $subQuery->whereBetween('punch_time', [
                                $filter['date_range']['start'],
                                $filter['date_range']['end'],
                            ]);
                        }
                    };

                    $disagreementDates = Attendance::where('status', 'Discrepancy')
                        ->where($dateRangeCondition)
                        ->get()
                        ->map(fn ($record) => $record->punch_time->format('Y-m-d'))
                        ->unique();

                    // Show all records from those dates
                    if ($disagreementDates->isNotEmpty()) {
                        $query->whereIn(
                            DB::raw('DATE(punch_time)'),
                            $disagreementDates
                        );
                    } else {
                        // If no consensus review records found, show none
                        $query->whereRaw('1 = 0');
                    }
                }

                // Apply `is_migrated` filter
                if ($filter && isset($filter['is_migrated'])) {
                    $query->where('is_migrated', $filter['is_migrated']);
                }
            })
            ->columns([
                TextColumn::make('employee.first_name')
                    ->label('Employee')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('employee.external_id')
                    ->label('Employee External ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('device.device_name')
                    ->label('Device')
                    ->sortable()
                    ->searchable(),

                TextInputColumn::make('punch_time')
                    ->label('Punch Time')
                    ->rules(['required', 'date_format:Y-m-d H:i:s'])
                    ->placeholder('YYYY-MM-DD HH:MM')
                    ->sortable()
                    ->searchable()
                    ->extraAttributes(['class' => 'editable']),

                SelectColumn::make('punch_type_id')
                    ->label('Punch Type')
                    ->options(PunchType::pluck('name', 'id'))
                    ->sortable()
                    ->searchable()
                    ->extraAttributes(['class' => 'editable']),

                SelectColumn::make('classification_id')
                    ->label('Classification')
                    ->options(Classification::where('is_active', 1)->pluck('name', 'id'))
                    ->sortable()
                    ->searchable()
                    ->extraAttributes(['class' => 'editable']),

                SelectColumn::make('status')
                    ->label('Status')
                    ->options(fn () => Attendance::getStatusOptions())
                    ->sortable()
                    ->searchable()
                    ->extraAttributes(['class' => 'editable']),

                TextInputColumn::make('issue_notes')
                    ->label('Issue Notes')
                    ->placeholder('Enter notes...')
                    ->sortable()
                    ->searchable()
                    ->extraAttributes(['class' => 'editable']),

                IconColumn::make('is_manual')
                    ->label('Manual Entry')
                    ->boolean(),

                IconColumn::make('is_migrated')
                    ->label('Migrated')
                    ->boolean()
                    ->trueIcon('heroicon-s-check-circle')
                    ->falseIcon('heroicon-s-x-circle')
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ]),
            ])
            ->filters([
                SelectFilter::make('device_id')
                    ->label('Device')
                    ->relationship('device', 'device_name')
                    ->preload(),

                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'full_names'),

                SelectFilter::make('punch_type_id')
                    ->label('Punch Type')
                    ->relationship('punchType', 'name')
                    ->preload(),

                SelectFilter::make('classification_id')
                    ->label('Classification')
                    ->relationship('classification', 'name')
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(fn () => Attendance::getStatusOptions())
                    ->multiple(),

                TernaryFilter::make('is_manual')
                    ->label('Manual Entry')
                    ->boolean()
                    ->trueLabel('Manual Only')
                    ->falseLabel('Automatic Only')
                    ->placeholder('All Entries'),

                TernaryFilter::make('is_migrated')
                    ->label('Migration Status')
                    ->boolean()
                    ->trueLabel('Migrated Only')
                    ->falseLabel('Not Migrated Only')
                    ->placeholder('All Records'),

                Filter::make('punch_time')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From Date'),
                        DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('punch_time', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('punch_time', '<=', $date),
                            );
                    }),

                Filter::make('pay_period')
                    ->schema([
                        Select::make('pay_period_id')
                            ->label('Pay Period')
                            ->options(function () {
                                return PayPeriod::orderBy('start_date', 'desc')
                                    ->limit(10)
                                    ->get()
                                    ->mapWithKeys(function ($period) {
                                        return [$period->id => $period->start_date->format('M j').' - '.$period->end_date->format('M j, Y')];
                                    });
                            })
                            ->searchable()
                            ->placeholder('Select Pay Period'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['pay_period_id'],
                            function (Builder $query, $payPeriodId): Builder {
                                $payPeriod = PayPeriod::find($payPeriodId);
                                if ($payPeriod) {
                                    return $query->whereBetween('punch_time', [
                                        $payPeriod->start_date->startOfDay(),
                                        $payPeriod->end_date->endOfDay(),
                                    ]);
                                }

                                return $query;
                            }
                        );
                    }),

                Filter::make('issues_only')
                    ->toggle()
                    ->label('Issues Only')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', ['NeedsReview', 'Incomplete', 'Discrepancy'])),

                Filter::make('consensus_review')
                    ->toggle()
                    ->label('Consensus Review Required')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'Discrepancy')),

                Filter::make('show_archived')
                    ->toggle()
                    ->label('Show Archived Records')
                    ->query(fn (Builder $query): Builder => $query), // Query handled in modifyQueryUsing
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('punch_time', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendances::route('/'),
            'create' => CreateAttendance::route('/create'),
            'edit' => EditAttendance::route('/{record}/edit'),
        ];
    }
}
