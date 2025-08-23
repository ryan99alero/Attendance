<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use App\Filament\Resources\VacationCalendarResource\Pages\ListVacationCalendars;
use App\Filament\Resources\VacationCalendarResource\Pages\CreateVacationCalendar;
use App\Filament\Resources\VacationCalendarResource\Pages\EditVacationCalendar;
use Filament\Forms\Get;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use App\Filament\Resources\VacationCalendarResource\Pages;
use App\Models\VacationCalendar;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VacationCalendarResource extends Resource
{
    protected static ?string $model = VacationCalendar::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Vacation Calendars';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)
                ->schema([
                    Toggle::make('is_active')
                        ->label('Active')
                        ->live()
                        ->columnSpan(0)
                        ->default(true),
                    Toggle::make('is_half_day')
                        ->label('Half Day')
                        ->live()
                        ->columnSpan(0)
                        ->default(false),
                    Toggle::make('vacation_pay')
                        ->label('Group Vacation')
                        ->columnSpan(0)
                        ->live()
                        ->default(false),
                ]),
            Select::make('employee_id')
                ->relationship('employee', 'first_name')
                ->label('Employee')
                ->required()
                ->preload()
                ->searchable()
                ->hidden(
                    fn ($get): bool => $get('vacation_pay') == true
                ),
            DatePicker::make('start_date')
                ->label('Start Date')
                ->required()
                ->prefix('No Weekends')
                ->disabledDates([
                    'Sat', 'Sun', // Disable all weekends
                    'before ' . now()->format('Y-m-d'), // Disable dates before today
                    'after ' . now()->addDays(5)->format('Y-m-d'), // Disable dates after 5 days from now
                ]),
            DatePicker::make('end_date')
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
            TextColumn::make('employee.first_name')->label('Employee'),
            TextColumn::make('vacation_date')->label('Vacation Date'),
            IconColumn::make('is_half_day')
                ->label('Half Day')
                ->boolean(),
            IconColumn::make('is_active')
                ->label('Active')
                ->boolean(),
            IconColumn::make('is_recorded')
                ->label('Time Recorded')
                ->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVacationCalendars::route('/'),
            'create' => CreateVacationCalendar::route('/create'),
            'edit' => EditVacationCalendar::route('/{record}/edit'),
        ];
    }
}
