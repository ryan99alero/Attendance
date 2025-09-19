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
    protected static ?string $navigationGroup = 'Time Off Management';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Vacation Request')
                ->schema([
                    Forms\Components\Select::make('employee_id')
                        ->relationship('employee', 'first_name')
                        ->label('Employee')
                        ->required()
                        ->preload()
                        ->searchable()
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_names),

                    Forms\Components\DatePicker::make('vacation_date')
                        ->label('Vacation Date')
                        ->required(),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Toggle::make('is_half_day')
                                ->label('Half Day')
                                ->default(false),

                            Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('employee.full_names')
                ->label('Employee')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('vacation_date')
                ->label('Vacation Date')
                ->date()
                ->sortable(),

            Tables\Columns\IconColumn::make('is_half_day')
                ->label('Half Day')
                ->boolean(),

            Tables\Columns\IconColumn::make('is_active')
                ->label('Active')
                ->boolean(),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Created')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->defaultSort('vacation_date', 'desc')
        ->filters([
            Tables\Filters\SelectFilter::make('employee_id')
                ->relationship('employee', 'first_name')
                ->searchable()
                ->preload(),

            Tables\Filters\TernaryFilter::make('is_half_day')
                ->label('Half Day')
                ->placeholder('All entries')
                ->trueLabel('Half days only')
                ->falseLabel('Full days only'),

            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Active Status')
                ->placeholder('All entries')
                ->trueLabel('Active only')
                ->falseLabel('Inactive only'),
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
