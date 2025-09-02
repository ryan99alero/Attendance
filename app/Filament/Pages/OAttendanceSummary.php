<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Device;
use App\Models\PayPeriod;
use App\Models\PunchType;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Pages\Page;
use Filament\Tables;
// Using basic Actions for table interactions
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OAttendanceSummary extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    /** Navigation */
    protected static \BackedEnum|string|null $navigationIcon  = 'heroicon-o-table-cells';
    protected static ?string $navigationLabel = 'O_AttendanceSummary';
    protected static \UnitEnum|string|null $navigationGroup = 'Punch & Attendance';
    protected static ?int    $navigationSort  = 50;

    /** View (NON-static in Filament v4) */
    protected string $view = 'filament.pages.o-attendance-summary';

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    /** TABLE */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->defaultSort('shift_date', 'desc')
            ->paginated([25, 50, 100])
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_names')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('shift_date')
                    ->label('Shift Date')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('punch_time')
                    ->label('Punch Time')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('H:i:s') : null)
                    ->sortable(),

                Tables\Columns\TextColumn::make('punch_type_id')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ((int) $state) {
                        1 => 'success',        // Start
                        2 => 'danger',         // Stop
                        3 => 'warning',        // Lunch Start
                        4 => 'info',           // Lunch Stop
                        default => 'gray',     // Unclassified / others
                    })
                    ->formatStateUsing(function ($state) {
                        // Prefer Punch Types table when available
                        if ($state && ($pt = PunchType::query()->find($state))) {
                            return $pt->name;
                        }

                        // Fall back to common mappings
                        return match ((int) $state) {
                            1 => 'Start',
                            2 => 'Stop',
                            3 => 'Lunch Start',
                            4 => 'Lunch Stop',
                            default => 'Unclassified',
                        };
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('punch_state')
                    ->label('Punch State')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'start'   => 'success',
                        'stop'    => 'danger',
                        'unknown' => 'gray',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'Migrated'    => 'success',
                        'NeedsReview' => 'warning',
                        'Partial'     => 'warning',
                        'Complete'    => 'success',
                        'Posted'      => 'info',
                        'Incomplete'  => 'gray',
                        default       => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('device.device_name')
                    ->label('Device')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                /** Status select (matches enum in schema) */
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Incomplete'  => 'Incomplete',
                        'Partial'     => 'Partial',
                        'Complete'    => 'Complete',
                        'Migrated'    => 'Migrated',
                        'Posted'      => 'Posted',
                        'NeedsReview' => 'Needs Review',
                    ])
                    ->native(false),

                /** Filter by Pay Period (id -> date range) */
                SelectFilter::make('pay_period')
                    ->label('Pay Period')
                    ->options(
                        PayPeriod::query()
                            ->orderByDesc('start_date')
                            ->get()
                            ->mapWithKeys(fn ($pp) => [
                                (string) $pp->id => "{$pp->start_date} → {$pp->end_date}",
                            ])
                            ->toArray()
                    )
                    ->query(function (Builder $query, array $data): void {
                        if (! filled($data['value'])) return;

                        $pp = PayPeriod::find($data['value']);
                        if ($pp) {
                            $query->whereBetween('shift_date', [$pp->start_date, $pp->end_date]);
                        }
                    })
                    ->native(false),

                /** Date range filter (ad-hoc) */
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('shift_date', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('shift_date', '<=', $date));
                    }),

                /** Only duplicates: same (employee_id, shift_date, punch_type_id) appears > 1 */
                Filter::make('duplicates_only')
                    ->label('Duplicates only')
                    ->toggle()
                    ->query(function (Builder $q): void {
                        // Use EXISTS to find duplicate keys
                        $q->whereExists(function ($sub) {
                            $sub->selectRaw('1')
                                ->from('attendances as a2')
                                ->whereColumn('a2.employee_id', 'attendances.employee_id')
                                ->whereColumn('a2.shift_date', 'attendances.shift_date')
                                ->whereColumn('a2.punch_type_id', 'attendances.punch_type_id')
                                ->whereRaw('a2.id <> attendances.id');
                        });
                    }),
            ])
            ->headerActions([
                Action::make('add_time_record')
                    ->label('Add Time Record')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Add Time Record')
                    ->form($this->createEditForm())
                    ->action(function (array $data) {
                        Attendance::create([
                            'employee_id'       => $data['employee_id'],
                            'device_id'         => $data['device_id'] ?? null,
                            'employee_external_id' => Employee::query()->whereKey($data['employee_id'])->value('external_id'),
                            'shift_date'        => $data['shift_date'],
                            'punch_time'        => "{$data['shift_date']} {$data['punch_time']}",
                            'punch_type_id'     => (int) $data['punch_type_id'],
                            'punch_state'       => $data['punch_state'],
                            'status'            => $data['status'] ?? 'Incomplete',
                            'issue_notes'       => $data['issue_notes'] ?? null,
                        ]);
                    })
                    ->closeModalByClickingAway(false)
                    ->modalWidth('md'),
            ])
            // Table actions are complex in Filament v4 - removing for now
            ->emptyStateHeading('No attendance records')
            ->emptyStateDescription('Try adjusting filters or add a new time record.')
            ->emptyStateActions([
                Action::make('empty_add_time_record')
                    ->label('Add Time Record')
                    ->icon('heroicon-o-plus')
                    ->form($this->createEditForm())
                    ->action(function (array $data) {
                        Attendance::create([
                            'employee_id'       => $data['employee_id'],
                            'device_id'         => $data['device_id'] ?? null,
                            'employee_external_id' => Employee::query()->whereKey($data['employee_id'])->value('external_id'),
                            'shift_date'        => $data['shift_date'],
                            'punch_time'        => "{$data['shift_date']} {$data['punch_time']}",
                            'punch_type_id'     => (int) $data['punch_type_id'],
                            'punch_state'       => $data['punch_state'],
                            'status'            => $data['status'] ?? 'Incomplete',
                            'issue_notes'       => $data['issue_notes'] ?? null,
                        ]);
                    }),
            ]);
    }

    /** Base query aligned to your schema */
    protected function baseQuery(): Builder
    {
        return Attendance::query()
            ->with([
                'employee:id,full_names,external_id',
                'device:id,device_name',
            ])
            ->select([
                'id',
                'employee_id',
                'device_id',
                'employee_external_id',
                'shift_date',
                'punch_time',
                'punch_type_id',
                'punch_state',
                'status',
                'issue_notes',
            ]);
    }

    /** Reusable Filament form schema for create/edit */
    protected function createEditForm(): array
    {
        return [
            Select::make('employee_id')
                ->label('Employee')
                ->options(
                    Employee::query()
                        ->orderBy('full_names')
                        ->pluck('full_names', 'id')
                        ->toArray()
                )
                ->searchable()
                ->preload()
                ->required(),

            Select::make('device_id')
                ->label('Device')
                ->options(
                    Device::query()
                        ->orderBy('device_name')
                        ->pluck('device_name', 'id')
                        ->toArray()
                )
                ->searchable()
                ->preload()
                ->native(false)
                ->visible(fn () => Device::query()->exists()),

            DatePicker::make('shift_date')
                ->label('Shift Date')
                ->required()
                ->native(false),

            TimePicker::make('punch_time')
                ->label('Punch Time')
                ->seconds() // HH:MM:SS
                ->required()
                ->native(false),

            Select::make('punch_type_id')
                ->label('Punch Type')
                ->options(
                    PunchType::query()
                        ->where('is_active', 1)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                )
                ->required()
                ->native(false),

            Select::make('punch_state')
                ->label('Punch State')
                ->options([
                    'start'   => 'Start',
                    'stop'    => 'Stop',
                    'unknown' => 'Unknown',
                ])
                ->required()
                ->native(false)
                ->default('unknown'),

            Select::make('status')
                ->label('Status')
                ->options([
                    'Incomplete'  => 'Incomplete',
                    'Partial'     => 'Partial',
                    'Complete'    => 'Complete',
                    'Migrated'    => 'Migrated',
                    'Posted'      => 'Posted',
                    'NeedsReview' => 'Needs Review',
                ])
                ->native(false)
                ->default('Incomplete'),

            Textarea::make('issue_notes')
                ->label('Issue Notes')
                ->rows(2)
                ->maxLength(255),
        ];
    }

    /** Optional: static titles/labels (strict return types for v4) */
    public static function getNavigationLabel(): string
    {
        return 'O_AttendanceSummary';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Punch & Attendance';
    }

    public static function getNavigationSort(): int
    {
        return 50;
    }
}
