<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Schedule;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            // General Information
            Forms\Components\TextInput::make('schedule_name')
                ->label('Schedule Name')
                ->required()
                ->maxLength(255),

            // Time-Related Fields
            Forms\Components\TimePicker::make('start_time')
                ->label('Start Time')
                ->required()
                ->seconds(false),
            Forms\Components\TimePicker::make('lunch_start_time')
                ->label('Lunch Start Time')
                ->nullable()
                ->seconds(false),
            Forms\Components\TextInput::make('lunch_duration')
                ->label('Lunch Duration (Minutes)')
                ->numeric()
                ->default(60),
            Forms\Components\TimePicker::make('end_time')
                ->label('End Time')
                ->required()
                ->seconds(false),
            Forms\Components\TextInput::make('daily_hours')
                ->label('Estimated Daily Hours')
                ->numeric()
                ->default(8),

            // Grace Period
            Forms\Components\TextInput::make('grace_period')
                ->label('Grace Period (Minutes)')
                ->numeric()
                ->default(0),

            // Relational Fields
            Forms\Components\Select::make('department_id')
                ->label('Department')
                ->relationship('department', 'name')
                ->searchable()
                ->nullable(),
            Forms\Components\Select::make('shift_id')
                ->label('Shift')
                ->relationship('shift', 'shift_name')
                ->nullable(),
            Forms\Components\Select::make('employee_id')
                ->label('Employee')
                ->relationship('employee', 'full_names')
                ->searchable()
                ->nullable(),

            // Miscellaneous
            Forms\Components\Textarea::make('notes')
                ->label('Notes')
                ->nullable(),
            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('schedule_name')
                ->label('Name')
                ->searchable(),
            Tables\Columns\TextColumn::make('start_time')
                ->label('Start Time')
                ->dateTime('H:i'),
            Tables\Columns\TextColumn::make('lunch_start_time')
                ->label('Lunch Start Time')
                ->dateTime('H:i')
                ->placeholder('N/A'),
            Tables\Columns\TextColumn::make('end_time')
                ->label('End Time')
                ->dateTime('H:i'),
            Tables\Columns\TextColumn::make('daily_hours')
                ->label('Hours'),
            Tables\Columns\TextColumn::make('grace_period')
                ->label('Grace Period (Minutes)'),
            Tables\Columns\TextColumn::make('department.name')
                ->label('Department')
                ->sortable(),
            Tables\Columns\TextColumn::make('shift.shift_name')
                ->label('Shift')
                ->sortable(),
            Tables\Columns\TextColumn::make('employees')
                ->label('Employees')
                ->getStateUsing(fn ($record) => $record->employees ? $record->employees->pluck('full_names')->join(', ') : 'N/A'),
            Tables\Columns\IconColumn::make('is_active')
                ->label('Active'),
        ])
            ->filters([
                Tables\Filters\Filter::make('is_active')
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchedules::route('/'),
            'create' => Pages\CreateSchedule::route('/create'),
            'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }
}
