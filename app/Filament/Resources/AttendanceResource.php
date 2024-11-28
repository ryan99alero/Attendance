<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Attendances';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('employee_id')
                ->relationship('employee', 'first_name') // Ensure this matches the `employee()` relationship in the model
                ->label('Employee')
                ->required(),
            Select::make('device_id')
                ->relationship('device', 'device_name') // Ensure this matches the `device()` relationship in the model
                ->label('Device')
                ->nullable(),
            DateTimePicker::make('check_in')
                ->label('Check-In Time')
                ->required(),
            DateTimePicker::make('check_out')
                ->label('Check-Out Time'),
            Toggle::make('is_manual')
                ->label('Manually Recorded'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('employee.first_name')
                ->label('Employee')
                ->sortable()
                ->searchable(),
            TextColumn::make('device.device_name')
                ->label('Device')
                ->sortable()
                ->searchable(),
            TextColumn::make('check_in')
                ->dateTime('M d, Y h:i A') // Example custom format
                ->label('Check-In'),
            TextColumn::make('check_out')
                ->dateTime('M d, Y h:i A') // Example custom format
                ->label('Check-Out'),
            IconColumn::make('is_manual')
                ->label('Manual Entry')
                ->boolean(),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
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
