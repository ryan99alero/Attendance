<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\Filter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ScheduleResource\Pages\ListSchedules;
use App\Filament\Resources\ScheduleResource\Pages\CreateSchedule;
use App\Filament\Resources\ScheduleResource\Pages\EditSchedule;
use App\Filament\Resources\ScheduleResource\RelationManagers\EmployeesRelationManager;
use UnitEnum;
use BackedEnum;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\ShiftSchedule;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class ShiftScheduleResource extends Resource
{
    protected static ?string $model = ShiftSchedule::class;

    // Navigation Configuration
    protected static string | \UnitEnum | null $navigationGroup = 'Scheduling & Shifts';
    protected static ?string $navigationLabel = 'Shift Schedules';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // General Information
            TextInput::make('schedule_name')
                ->label('ShiftSchedule Name')
                ->required()
                ->maxLength(255),

            // Time-Related Fields
            TimePicker::make('start_time')
                ->label('Start Time')
                ->required()
                ->seconds(false),
            TimePicker::make('lunch_start_time')
                ->label('Lunch Start Time')
                ->nullable()
                ->seconds(false),
            TimePicker::make('lunch_stop_time')
                ->label('Lunch Stop Time')
                ->nullable()
                ->seconds(false),
            TextInput::make('lunch_duration')
                ->label('Lunch Duration (Minutes)')
                ->numeric()
                ->default(60),
            TimePicker::make('end_time')
                ->label('End Time')
                ->required()
                ->seconds(false),
            TextInput::make('daily_hours')
                ->label('Estimated Daily Hours')
                ->numeric()
                ->default(8),

            // Grace Period
            TextInput::make('grace_period')
                ->label('Grace Period (Minutes)')
                ->numeric()
                ->default(0),

            // Relational Fields
            Select::make('shift_id')
                ->label('Shift')
                ->relationship('shift', 'shift_name')
                ->nullable(),
            // employee_id removed - employees now reference shift_schedule_id instead

            // Miscellaneous
            Textarea::make('notes')
                ->label('Notes')
                ->nullable(),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('schedule_name')
                ->label('Name')
                ->searchable()
                ->sortable(),
            TextColumn::make('start_time')
                ->label('Start Time')
                ->dateTime('H:i')
                ->sortable(),
            TextColumn::make('lunch_start_time')
                ->label('Lunch Start Time')
                ->dateTime('H:i')
                ->placeholder('N/A')
                ->sortable(),
            TextColumn::make('lunch_stop_time')
                ->label('Lunch Stop Time')
                ->dateTime('H:i')
                ->placeholder('N/A')
                ->sortable(),
            TextColumn::make('end_time')
                ->label('End Time')
                ->dateTime('H:i')
                ->sortable(),
            TextColumn::make('daily_hours')
                ->label('Hours')
                ->sortable(),
            TextColumn::make('grace_period')
                ->label('Grace Period (Minutes)')
                ->sortable(),
            TextColumn::make('shift.shift_name')
                ->label('Shift')
                ->sortable(),
            TextColumn::make('employees_count')
                ->label('Employees')
                ->counts('employees')
                ->sortable(),
            IconColumn::make('is_active')
                ->label('Active')
                ->sortable(),
        ])
            ->filters([
                Filter::make('is_active')
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            EmployeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchedules::route('/'),
            'create' => CreateSchedule::route('/create'),
            'edit' => EditSchedule::route('/{record}/edit'),
        ];
    }
}
