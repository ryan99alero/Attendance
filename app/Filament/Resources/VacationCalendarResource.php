<?php

namespace App\Filament\Resources;

use Filament\Forms\Get;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use App\Filament\Resources\VacationCalendarResource\Pages;
use App\Models\VacationCalendar;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VacationCalendarResource extends Resource
{
    protected static ?string $model = VacationCalendar::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Vacation Calendars';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->live()
                        ->columnSpan(0)
                        ->default(true),
                    Forms\Components\Toggle::make('is_half_day')
                        ->label('Half Day')
                        ->live()
                        ->columnSpan(0)
                        ->default(false),
                    Forms\Components\Toggle::make('vacation_pay')
                        ->label('Group Vacation')
                        ->columnSpan(0)
                        ->live()
                        ->default(false),
                ]),
            Forms\Components\Select::make('employee_id')
                ->relationship('employee', 'first_name')
                ->label('Employee')
                ->required()
                ->preload()
                ->searchable()
                ->hidden(
                    fn ($get): bool => $get('vacation_pay') == true
                ),
            Forms\Components\DatePicker::make('start_date')
                ->label('Start Date')
                ->required()
                ->prefix('No Weekends')
                ->disabledDates([
                    'Sat', 'Sun', // Disable all weekends
                    'before ' . now()->format('Y-m-d'), // Disable dates before today
                    'after ' . now()->addDays(5)->format('Y-m-d'), // Disable dates after 5 days from now
                ]),
            Forms\Components\DatePicker::make('end_date')
                ->label('End Date')
                ->required()
                ->prefix('No Weekends')
                ->afterOrEqual('start_date')
                ->disabledDates([
                    'Sat', 'Sun', // Disable all weekends
                    'before ' . now()->format('Y-m-d'), // Disable dates before today
                    'after ' . now()->addDays(5)->format('Y-m-d'), // Disable dates after 5 days from now
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('employee.first_name')->label('Employee'),
            Tables\Columns\TextColumn::make('vacation_date')->label('Vacation Date'),
            Tables\Columns\IconColumn::make('is_half_day')
                ->label('Half Day')
                ->boolean(),
            Tables\Columns\IconColumn::make('is_active')
                ->label('Active')
                ->boolean(),
            Tables\Columns\IconColumn::make('is_recorded')
                ->label('Time Recorded')
                ->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVacationCalendars::route('/'),
            'create' => Pages\CreateVacationCalendar::route('/create'),
            'edit' => Pages\EditVacationCalendar::route('/{record}/edit'),
        ];
    }
}
