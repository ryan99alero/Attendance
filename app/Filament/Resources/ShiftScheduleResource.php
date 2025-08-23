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
use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\ShiftSchedule;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class ShiftScheduleResource extends Resource
{
    protected static ?string $model = ShiftSchedule::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

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
            Select::make('department_id')
                ->label('Department')
                ->relationship('department', 'name')
                ->searchable()
                ->nullable(),
            Select::make('shift_id')
                ->label('Shift')
                ->relationship('shift', 'shift_name')
                ->nullable(),
            Select::make('employee_id')
                ->label('Employee')
                ->relationship('employee', 'full_names')
                ->searchable()
                ->nullable(),

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
                ->searchable(),
            TextColumn::make('start_time')
                ->label('Start Time')
                ->dateTime('H:i'),
            TextColumn::make('lunch_start_time')
                ->label('Lunch Start Time')
                ->dateTime('H:i')
                ->placeholder('N/A'),
            TextColumn::make('lunch_stop_time')
                ->label('Lunch Stop Time')
                ->dateTime('H:i')
                ->placeholder('N/A'),
            TextColumn::make('end_time')
                ->label('End Time')
                ->dateTime('H:i'),
            TextColumn::make('daily_hours')
                ->label('Hours'),
            TextColumn::make('grace_period')
                ->label('Grace Period (Minutes)'),
            TextColumn::make('department.name')
                ->label('Department')
                ->sortable(),
            TextColumn::make('shift.shift_name')
                ->label('Shift')
                ->sortable(),
            TextColumn::make('employees')
                ->label('Employees')
                ->getStateUsing(fn ($record) => $record->employees ? $record->employees->pluck('full_names')->join(', ') : 'N/A'),
            IconColumn::make('is_active')
                ->label('Active'),
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

    public static function getPages(): array
    {
        return [
            'index' => ListSchedules::route('/'),
            'create' => CreateSchedule::route('/create'),
            'edit' => EditSchedule::route('/{record}/edit'),
        ];
    }
}
