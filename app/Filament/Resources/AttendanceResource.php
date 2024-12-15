<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Carbon\Carbon;

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
                ->relationship('employee', 'first_name') // Relationship to Employee
                ->label('Employee')
                ->required(),
            Select::make('device_id')
                ->relationship('device', 'device_name') // Relationship to Device
                ->label('Device')
                ->nullable(),
            DateTimePicker::make('punch_time')
                ->label('Punch Time')
                ->seconds(false)
                ->displayFormat('Y-m-d H:i')
                ->required(),
            Toggle::make('is_manual')
                ->label('Manually Recorded'),
            TextInput::make('status')
                ->label('Status')
                ->placeholder('Pending, Reviewed, Fixed')
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
        return $table->columns([
            TextColumn::make('employee.first_name')
                ->label('Employee')
                ->sortable()
                ->searchable(),

            TextColumn::make('device.device_name')
                ->label('Device')
                ->sortable()
                ->searchable(),

            TextInputColumn::make('punch_time')
                ->label('punch_time')
                ->rules(['required', 'date_format:Y-m-d H:i']) // Validation rule for datetime
                ->placeholder('YYYY-MM-DD HH:MM')
                ->searchable(),

            TextColumn::make('status')
                ->label('Status')
                ->sortable(),

            TextColumn::make('issue_notes')
                ->label('Issue Notes')
                ->limit(50) // Truncate notes for display
                ->tooltip(fn ($state) => $state), // Full notes as tooltip

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
