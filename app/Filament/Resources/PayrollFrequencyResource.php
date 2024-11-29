<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollFrequencyResource\Pages;
use App\Models\PayrollFrequency;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Form;
use Filament\Tables\Table;

class PayrollFrequencyResource extends Resource
{
    protected static ?string $model = PayrollFrequency::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Payroll Frequencies';

    /**
     * Define the form schema for creating/editing records.
     *
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('frequency_name')
                ->label('Frequency Name')
                ->required()
                ->placeholder('e.g., Weekly, Monthly, Custom'),
            Select::make('weekly_day')
                ->label('Weekly Payroll Day')
                ->options([
                    '0' => 'Sunday',
                    '1' => 'Monday',
                    '2' => 'Tuesday',
                    '3' => 'Wednesday',
                    '4' => 'Thursday',
                    '5' => 'Friday',
                    '6' => 'Saturday',
                ])
                ->nullable()
                ->placeholder('Select a day'),
            TextInput::make('semimonthly_first_day')
                ->label('Semi-Monthly First Day')
                ->numeric()
                ->minValue(1)
                ->maxValue(31)
                ->nullable()
                ->placeholder('e.g., 1'),
            TextInput::make('semimonthly_second_day')
                ->label('Semi-Monthly Second Day')
                ->numeric()
                ->minValue(1)
                ->maxValue(31)
                ->nullable()
                ->placeholder('e.g., 15'),
            TextInput::make('monthly_day')
                ->label('Monthly Payroll Day')
                ->numeric()
                ->minValue(1)
                ->maxValue(31)
                ->nullable()
                ->placeholder('e.g., 30'),
        ]);
    }

    /**
     * Define the table schema for listing records.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('frequency_name')
                ->label('Frequency Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('weekly_day')
                ->label('Weekly Day')
                ->formatStateUsing(fn ($state) => match ($state) {
                    '0' => 'Sunday',
                    '1' => 'Monday',
                    '2' => 'Tuesday',
                    '3' => 'Wednesday',
                    '4' => 'Thursday',
                    '5' => 'Friday',
                    '6' => 'Saturday',
                    default => null,
                }),
            TextColumn::make('semimonthly_first_day')
                ->label('Semi-Monthly First Day'),
            TextColumn::make('semimonthly_second_day')
                ->label('Semi-Monthly Second Day'),
            TextColumn::make('monthly_day')
                ->label('Monthly Day'),
            TextColumn::make('created_at')
                ->label('Created At')
                ->dateTime(),
            TextColumn::make('updated_at')
                ->label('Updated At')
                ->dateTime(),
        ]);
    }

    /**
     * Define the available pages for this resource.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollFrequencies::route('/'),
            'create' => Pages\CreatePayrollFrequency::route('/create'),
            'edit' => Pages\EditPayrollFrequency::route('/{record}/edit'),
        ];
    }
}
