<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use App\Models\PunchType;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
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
                ->relationship('employee', 'first_name')
                ->label('Employee')
                ->required(),
            Select::make('device_id')
                ->relationship('device', 'device_name')
                ->label('Device')
                ->nullable(),
            DateTimePicker::make('punch_time')
                ->label('Punch Time')
                ->seconds(false)
                ->displayFormat('Y-m-d H:i')
                ->required(),
            Select::make('punch_type_id')
                ->label('Punch Type')
                ->options(PunchType::pluck('name', 'id')->toArray())
                ->nullable()
                ->searchable(),
            Toggle::make('is_manual')
                ->label('Manually Recorded')
                ->default(true), // Set the default value to true
            Select::make('status')
                ->label('Status')
                ->options(function () {
                    $type = DB::selectOne("SHOW COLUMNS FROM `attendances` WHERE Field = 'status'")->Type;

                    preg_match('/^enum\((.*)\)$/', $type, $matches);
                    $enumOptions = array_map(function ($value) {
                        return trim($value, "'");
                    }, explode(',', $matches[1]));

                    return array_combine($enumOptions, $enumOptions);
                })
                ->required(),
            Textarea::make('issue_notes')
                ->label('Issue Notes')
                ->rows(3)
                ->placeholder('Enter details about any issues or anomalies'),
            Toggle::make('is_migrated')
                ->label('Punch Recorded'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) {
                // Check for 'filter_ids' in the request URL
                if (request()->has('filter_ids')) {
                    $ids = explode(',', request()->get('filter_ids'));
                    $query->whereIn('id', $ids);
                }
            })
            ->columns([
                TextColumn::make('employee.first_name')
                    ->label('Employee')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('device.device_name')
                    ->label('Device')
                    ->sortable()
                    ->searchable(),

                TextInputColumn::make('punch_time')
                    ->label('Punch Time')
                    ->rules(['required', 'date_format:Y-m-d H:i'])
                    ->placeholder('YYYY-MM-DD HH:MM')
                    ->sortable()
                    ->searchable()
                    ->extraAttributes(['class' => 'editable']),

                SelectColumn::make('punch_type_id')
                    ->label('Punch Type')
                    ->options(PunchType::pluck('name', 'id')->toArray())
                    ->sortable()
                    ->searchable()
                    ->extraAttributes(['class' => 'editable']),

                SelectColumn::make('status')
                    ->label('Status')
                    ->options(function () {
                        $type = DB::selectOne("SHOW COLUMNS FROM `attendances` WHERE Field = 'status'")->Type;

                        preg_match('/^enum\((.*)\)$/', $type, $matches);
                        $enumOptions = array_map(function ($value) {
                            return trim($value, "'");
                        }, explode(',', $matches[1]));

                        return array_combine($enumOptions, $enumOptions);
                    })
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
