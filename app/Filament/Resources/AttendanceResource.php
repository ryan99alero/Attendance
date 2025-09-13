<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use App\Models\PunchType;
use App\Models\Employee;
use App\Models\PayPeriod;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Form;
use Filament\Tables\Table;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Attendances';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('employee_id')
                ->label('Employee')
                ->options(Employee::orderBy('last_name')->orderBy('first_name')
                    ->get()
                    ->mapWithKeys(fn($employee) => [$employee->id => "{$employee->last_name}, {$employee->first_name}"])
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

            Toggle::make('is_manual')
                ->label('Manually Recorded')
                ->default(true),

            Select::make('status')
                ->label('Status')
                ->options(fn () => Attendance::getStatusOptions())
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendance::route('/create'),
            'edit' => Pages\EditAttendance::route('/{record}/edit'),
        ];
    }
}
