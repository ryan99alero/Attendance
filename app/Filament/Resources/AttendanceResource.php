<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Carbon\Carbon;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Attendances';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('employee_id')
                ->relationship('employee', 'first_name') // Relationship to Employee
                ->label('Employee')
                ->required(),
            Select::make('device_id')
                ->relationship('device', 'device_name') // Relationship to Device
                ->label('Device')
                ->nullable(),
            DateTimePicker::make('check_in')
                ->label('Check-In Time')
                ->formatStateUsing(function ($state) {
                    return $state ? Carbon::parse($state)->format('Y-m-d\TH:i:s') : null;
                })
                ->required(),
            DateTimePicker::make('check_out')
                ->label('Check-Out Time')
                ->formatStateUsing(function ($state) {
                    return $state ? Carbon::parse($state)->format('Y-m-d\TH:i:s') : null;
                }),
            Toggle::make('is_manual')
                ->label('Manually Recorded'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('employee.first_name')
                ->label('Employee')
                ->alignCenter()
                ->sortable()
                ->searchable(),

            TextColumn::make('device.device_name')
                ->label('Device')
                ->alignCenter()
                ->sortable()
                ->searchable(),

            TextInputColumn::make('check_in')
                ->label('Check-In')
                ->alignCenter()
                ->rules(['required', 'date_format:Y-m-d H:i:s']) // Validation rule for datetime
                ->afterStateUpdated(fn ($state, $record) => $record->update(['check_in' => $state])) // Update the record
                ->placeholder('YYYY-MM-DD HH:MM:SS')
                ->searchable(),

            TextInputColumn::make('check_out')
                ->label('Check-Out')
                ->alignCenter()
                ->rules(['nullable', 'date_format:Y-m-d H:i:s'])
                ->afterStateUpdated(fn ($state, $record) => $record->update(['check_out' => $state]))
                ->placeholder('YYYY-MM-DD HH:MM:SS')
                ->searchable(),

            IconColumn::make('is_manual')
                ->label('Manual Entry')
                ->alignCenter()
                ->boolean(),
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
