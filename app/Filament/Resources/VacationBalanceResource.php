<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VacationBalanceResource\Pages;
use App\Models\VacationBalance;
use App\Models\CompanySetup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VacationBalanceResource extends Resource
{
    protected static ?string $model = VacationBalance::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Vacation Balances';
    protected static ?string $navigationGroup = 'Time Off Management';
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        $companySetup = CompanySetup::first();
        $vacationAccrualMethod = $companySetup?->vacation_accrual_method ?? 'anniversary';

        return $form->schema([
            Forms\Components\Select::make('employee_id')
                ->relationship('employee', 'first_name')
                ->label('Employee')
                ->required(),

            // Only show accrual_rate for pay_period method
            Forms\Components\TextInput::make('accrual_rate')
                ->label('Accrual Rate (Hours per Pay Period)')
                ->numeric()
                ->step(0.0001)
                ->required()
                ->visible($vacationAccrualMethod === 'pay_period')
                ->helperText('Hours of vacation accrued each pay period'),

            Forms\Components\TextInput::make('accrued_hours')
                ->label('Accrued Hours')
                ->numeric()
                ->step(0.01)
                ->required(),

            Forms\Components\TextInput::make('used_hours')
                ->label('Used Hours')
                ->numeric()
                ->step(0.01)
                ->required(),

            Forms\Components\TextInput::make('carry_over_hours')
                ->label('Carry Over Hours')
                ->numeric()
                ->step(0.01)
                ->nullable(),

            Forms\Components\TextInput::make('cap_hours')
                ->label('Cap Hours')
                ->numeric()
                ->step(0.01)
                ->required(),

            // Anniversary-specific fields
            Forms\Components\Section::make('Anniversary Information')
                ->schema([
                    Forms\Components\TextInput::make('accrual_year')
                        ->label('Current Service Year')
                        ->numeric()
                        ->disabled()
                        ->helperText('Years of service for this employee'),

                    Forms\Components\DatePicker::make('last_anniversary_date')
                        ->label('Last Anniversary Date')
                        ->disabled()
                        ->helperText('Date of most recent anniversary accrual'),

                    Forms\Components\DatePicker::make('next_anniversary_date')
                        ->label('Next Anniversary Date')
                        ->disabled()
                        ->helperText('Date of next scheduled anniversary'),

                    Forms\Components\TextInput::make('annual_days_earned')
                        ->label('Annual Days Earned')
                        ->numeric()
                        ->step(0.01)
                        ->disabled()
                        ->suffix('days')
                        ->helperText('Vacation days earned at last anniversary'),

                    Forms\Components\TextInput::make('current_year_awarded')
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
            Forms\Components\Section::make('Pay Period Information')
                ->schema([
                    Forms\Components\DatePicker::make('policy_effective_date')
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
            Tables\Columns\TextColumn::make('employee.full_names')
                ->label('Employee')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('accrued_hours')
                ->label('Accrued Hours')
                ->numeric(decimalPlaces: 2)
                ->suffix(' hrs')
                ->sortable(),

            Tables\Columns\TextColumn::make('used_hours')
                ->label('Used Hours')
                ->numeric(decimalPlaces: 2)
                ->suffix(' hrs')
                ->sortable(),

            Tables\Columns\TextColumn::make('available_hours')
                ->label('Available Hours')
                ->numeric(decimalPlaces: 2)
                ->suffix(' hrs')
                ->getStateUsing(fn ($record) => $record->accrued_hours - $record->used_hours)
                ->sortable()
                ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),

            Tables\Columns\TextColumn::make('carry_over_hours')
                ->label('Carry Over Hours')
                ->numeric(decimalPlaces: 2)
                ->suffix(' hrs')
                ->placeholder('None')
                ->sortable(),

            Tables\Columns\TextColumn::make('cap_hours')
                ->label('Cap Hours')
                ->numeric(decimalPlaces: 2)
                ->suffix(' hrs')
                ->sortable(),
        ];

        // Add method-specific columns
        if ($vacationAccrualMethod === 'pay_period') {
            $columns[] = Tables\Columns\TextColumn::make('accrual_rate')
                ->label('Accrual Rate')
                ->numeric(decimalPlaces: 4)
                ->suffix(' hrs/period')
                ->sortable();
        }

        if ($vacationAccrualMethod === 'anniversary') {
            array_splice($columns, 4, 0, [
                Tables\Columns\TextColumn::make('accrual_year')
                    ->label('Service Year')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('annual_days_earned')
                    ->label('Annual Days')
                    ->numeric(decimalPlaces: 1)
                    ->suffix(' days')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('last_anniversary_date')
                    ->label('Last Anniversary')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('next_anniversary_date')
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
            'index' => Pages\ListVacationBalances::route('/'),
            'create' => Pages\CreateVacationBalance::route('/create'),
            'edit' => Pages\EditVacationBalance::route('/{record}/edit'),
        ];
    }
}
