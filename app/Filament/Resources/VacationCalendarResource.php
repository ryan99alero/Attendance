<?php

namespace App\Filament\Resources;

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

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Vacation Calendars';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('employee_id')
                ->relationship('employee', 'first_name')
                ->label('Employee')
                ->required(),
            Forms\Components\DatePicker::make('vacation_date')
                ->label('Vacation Date')
                ->required(),
            Forms\Components\Toggle::make('is_half_day')
                ->label('Half Day')
                ->default(false),
            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('employee.first_name')->label('Employee'),
            Tables\Columns\TextColumn::make('vacation_date')->label('Vacation Date'),
            Tables\Columns\IconColumn::make('is_half_day')->label('Half Day'),
            Tables\Columns\IconColumn::make('is_active')->label('Active'),
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
