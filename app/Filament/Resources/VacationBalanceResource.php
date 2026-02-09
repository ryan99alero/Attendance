<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\VacationBalanceResource\Pages\ListVacationBalances;
use App\Filament\Resources\VacationBalanceResource\Pages\CreateVacationBalance;
use App\Filament\Resources\VacationBalanceResource\Pages\EditVacationBalance;
use UnitEnum;
use BackedEnum;

use App\Filament\Resources\VacationBalanceResource\Pages;
use App\Models\VacationBalance;
use App\Models\CompanySetup;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VacationBalanceResource extends Resource
{
    protected static ?string $model = VacationBalance::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Vacation Balances';
    protected static string | \UnitEnum | null $navigationGroup = 'Time Off Management';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        $companySetup = CompanySetup::first();
        $vacationAccrualMethod = $companySetup?->vacation_accrual_method ?? 'anniversary';

        return $schema->components([
            Select::make('employee_id')
                ->relationship('employee', 'first_name')
                ->label('Employee')
                ->required(),

            // Only show accrual_rate for pay_period method
            TextInput::make('accrual_rate')
                ->label('Accrual Rate (Hours per Pay Period)')
                ->numeric()
                ->step(0.0001)
                ->required()
                ->visible($vacationAccrualMethod === 'pay_period')
                ->helperText('Hours of vacation accrued each pay period'),

            TextInput::make('accrued_hours')
                ->label('Accrued Hours')
                ->numeric()
                ->step(0.01)
                ->required(),

            TextInput::make('used_hours')
                ->label('Used Hours')
                ->numeric()
                ->step(0.01)
                ->required(),

            TextInput::make('carry_over_hours')
                ->label('Carry Over Hours')
                ->numeric()
                ->step(0.01)
                ->nullable(),

            TextInput::make('cap_hours')
                ->label('Cap Hours')
                ->numeric()
                ->step(0.01)
                ->required(),

            // Anniversary-specific fields
            Section::make('Anniversary Information')
                ->schema([
                    TextInput::make('accrual_year')
                        ->label('Current Service Year')
                        ->numeric()
                        ->disabled()
                        ->helperText('Years of service for this employee'),

                    DatePicker::make('last_anniversary_date')
                        ->label('Last Anniversary Date')
                        ->disabled()
                        ->helperText('Date of most recent anniversary accrual'),

                    DatePicker::make('next_anniversary_date')
                        ->label('Next Anniversary Date')
                        ->disabled()
                        ->helperText('Date of next scheduled anniversary'),

                    TextInput::make('annual_days_earned')
                        ->label('Annual Days Earned')
                        ->numeric()
                        ->step(0.01)
                        ->disabled()
                        ->suffix('days')
                        ->helperText('Vacation days earned at last anniversary'),

                    TextInput::make('current_year_awarded')
                        ->label('Current Year Hours Awarded')
                        ->numeric()
                        ->step(0.01)
                        ->disabled()
                        ->suffix('hours')
                        ->helperText('Hours awarded at last anniversary'),
                ])
                ->visible($vacationAccrualMethod === 'anniversary')
                ->columns(2),

            // Pay Period specific fields
            Section::make('Pay Period Information')
                ->schema([
                    DatePicker::make('policy_effective_date')
                        ->label('Policy Effective Date')
                        ->helperText('Date when current accrual policy became effective'),
                ])
                ->visible($vacationAccrualMethod === 'pay_period')
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        $companySetup = CompanySetup::first();
        $vacationAccrualMethod = $companySetup?->vacation_accrual_method ?? 'anniversary';

        $columns = [
            TextColumn::make('employee.full_names')
                ->label('Employee')
                ->sortable()
                ->searchable(),

            TextColumn::make('accrued_hours')
                ->label('Accrued Hours')
                ->numeric(decimalPlaces: 2)
                ->suffix(' hrs')
                ->sortable(),

            TextColumn::make('used_hours')
                ->label('Used Hours')
                ->numeric(decimalPlaces: 2)
                ->suffix(' hrs')
                ->sortable(),

            TextColumn::make('available_hours')
                ->label('Available Hours')
                ->numeric(decimalPlaces: 2)
                ->suffix(' hrs')
                ->getStateUsing(fn ($record) => $record->accrued_hours - $record->used_hours)
                ->sortable()
                ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),

            TextColumn::make('carry_over_hours')
                ->label('Carry Over Hours')
                ->numeric(decimalPlaces: 2)
                ->suffix(' hrs')
                ->placeholder('None')
                ->sortable(),

            TextColumn::make('cap_hours')
                ->label('Cap Hours')
                ->numeric(decimalPlaces: 2)
                ->suffix(' hrs')
                ->sortable(),
        ];

        // Add method-specific columns
        if ($vacationAccrualMethod === 'pay_period') {
            $columns[] = TextColumn::make('accrual_rate')
                ->label('Accrual Rate')
                ->numeric(decimalPlaces: 4)
                ->suffix(' hrs/period')
                ->sortable();
        }

        if ($vacationAccrualMethod === 'anniversary') {
            array_splice($columns, 4, 0, [
                TextColumn::make('accrual_year')
                    ->label('Service Year')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('annual_days_earned')
                    ->label('Annual Days')
                    ->numeric(decimalPlaces: 1)
                    ->suffix(' days')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('last_anniversary_date')
                    ->label('Last Anniversary')
                    ->date()
                    ->sortable(),

                TextColumn::make('next_anniversary_date')
                    ->label('Next Anniversary')
                    ->date()
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'gray'),
            ]);
        }

        return $table->columns($columns);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVacationBalances::route('/'),
            'create' => CreateVacationBalance::route('/create'),
            'edit' => EditVacationBalance::route('/{record}/edit'),
        ];
    }
}
