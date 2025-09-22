<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollFrequencyResource\Pages;
use App\Models\PayrollFrequency;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms\Get;

class PayrollFrequencyResource extends Resource
{
    protected static ?string $model = PayrollFrequency::class;

    // Navigation Configuration
    protected static ?string $navigationGroup = 'Payroll & Overtime';
    protected static ?string $navigationLabel = 'Payroll Frequencies';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?int $navigationSort = 20;

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
                ->placeholder('e.g., Bi-Weekly Fridays, Monthly Last Day'),

            Select::make('frequency_type')
                ->label('Frequency Type')
                ->required()
                ->options([
                    'weekly' => 'Weekly',
                    'biweekly' => 'Bi-Weekly',
                    'semimonthly' => 'Semi-Monthly',
                    'monthly' => 'Monthly',
                ])
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('reference_start_date', null)),

            // Weekly Configuration
            Select::make('weekly_day')
                ->label('Pay Day (Day of Week)')
                ->helperText('Which day of the week employees are paid')
                ->options([
                    0 => 'Sunday',
                    1 => 'Monday',
                    2 => 'Tuesday',
                    3 => 'Wednesday',
                    4 => 'Thursday',
                    5 => 'Friday',
                    6 => 'Saturday',
                ])
                ->visible(fn (Get $get) => in_array($get('frequency_type'), ['weekly', 'biweekly']))
                ->required(fn (Get $get) => in_array($get('frequency_type'), ['weekly', 'biweekly'])),

            Select::make('start_of_week')
                ->label('Start of Work Week')
                ->helperText('Which day the work week begins (determines pay period boundaries)')
                ->options([
                    0 => 'Sunday',
                    1 => 'Monday',
                    2 => 'Tuesday',
                    3 => 'Wednesday',
                    4 => 'Thursday',
                    5 => 'Friday',
                    6 => 'Saturday',
                ])
                ->default(0)
                ->visible(fn (Get $get) => in_array($get('frequency_type'), ['weekly', 'biweekly']))
                ->required(fn (Get $get) => in_array($get('frequency_type'), ['weekly', 'biweekly'])),

            DatePicker::make('reference_start_date')
                ->label('Reference Start Date')
                ->helperText('Choose any date that falls on your desired pay day. This establishes the bi-weekly cycle.')
                ->visible(fn (Get $get) => $get('frequency_type') === 'biweekly')
                ->required(fn (Get $get) => $get('frequency_type') === 'biweekly'),

            // Semi-monthly Configuration
            Select::make('first_pay_day')
                ->label('First Pay Day')
                ->options(self::getPayDayOptions())
                ->visible(fn (Get $get) => in_array($get('frequency_type'), ['semimonthly', 'monthly']))
                ->required(fn (Get $get) => in_array($get('frequency_type'), ['semimonthly', 'monthly']))
                ->helperText('Choose a specific day (1-31) or special options'),

            Select::make('second_pay_day')
                ->label('Second Pay Day')
                ->options(self::getPayDayOptions())
                ->visible(fn (Get $get) => $get('frequency_type') === 'semimonthly')
                ->required(fn (Get $get) => $get('frequency_type') === 'semimonthly')
                ->helperText('Choose a specific day (1-31) or special options'),

            // Business Rules
            Select::make('month_end_handling')
                ->label('Short Month Handling')
                ->options([
                    'exact_day' => 'Keep exact day (may roll to next month)',
                    'last_day_of_month' => 'Move to last day of current month',
                    'first_day_next_month' => 'Move to first day of next month',
                ])
                ->default('last_day_of_month')
                ->visible(fn (Get $get) => in_array($get('frequency_type'), ['semimonthly', 'monthly']))
                ->helperText('What to do when pay day doesn\'t exist (e.g., Feb 31st)'),

            Select::make('weekend_adjustment')
                ->label('Weekend Adjustment')
                ->options([
                    'none' => 'No adjustment (pay on weekend)',
                    'previous_friday' => 'Move to previous Friday',
                    'next_monday' => 'Move to next Monday',
                    'closest_weekday' => 'Move to closest weekday',
                ])
                ->default('previous_friday')
                ->helperText('How to handle pay dates that fall on weekends'),

            Toggle::make('skip_holidays')
                ->label('Skip Holidays')
                ->helperText('Adjust pay dates that fall on company holidays (requires holiday calendar)')
                ->default(false),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),

            Textarea::make('description')
                ->label('Description')
                ->rows(3)
                ->placeholder('Optional description of this payroll frequency'),
        ]);
    }

    /**
     * Get pay day options with special codes
     */
    protected static function getPayDayOptions(): array
    {
        $options = [];

        // Add special options first (they'll appear at top)
        $options[99] = 'Last day of month (dynamic)';
        $options[98] = '1st day of next month (dynamic)';

        // Add a separator
        $options['separator'] = '--- Specific Days ---';

        // Add numbered days 1-31
        for ($i = 1; $i <= 31; $i++) {
            $options[$i] = $i . self::getOrdinalSuffix($i) . ' of month';
        }

        return $options;
    }

    /**
     * Get ordinal suffix for numbers (1st, 2nd, 3rd, etc.)
     */
    protected static function getOrdinalSuffix(int $number): string
    {
        if ($number >= 11 && $number <= 13) {
            return 'th';
        }

        return match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th'
        };
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
                ->label('Name')
                ->sortable()
                ->searchable(),

            TextColumn::make('frequency_type')
                ->label('Type')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'weekly' => 'success',
                    'biweekly' => 'info',
                    'semimonthly' => 'warning',
                    'monthly' => 'primary',
                    default => 'gray',
                })
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'weekly' => 'Weekly',
                    'biweekly' => 'Bi-Weekly',
                    'semimonthly' => 'Semi-Monthly',
                    'monthly' => 'Monthly',
                    default => ucfirst($state),
                }),

            TextColumn::make('pay_schedule')
                ->label('Pay Schedule')
                ->getStateUsing(function ($record) {
                    return match ($record->frequency_type) {
                        'weekly', 'biweekly' => $record->weekly_day !== null ?
                            ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$record->weekly_day] : '-',
                        'semimonthly' => self::formatPayDay($record->first_pay_day) . ' & ' . self::formatPayDay($record->second_pay_day),
                        'monthly' => self::formatPayDay($record->first_pay_day),
                        default => '-',
                    };
                }),

            TextColumn::make('weekend_adjustment')
                ->label('Weekend Rule')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'none' => 'No adjustment',
                    'previous_friday' => 'Previous Friday',
                    'next_monday' => 'Next Monday',
                    'closest_weekday' => 'Closest weekday',
                    default => ucfirst(str_replace('_', ' ', $state)),
                }),

            BooleanColumn::make('is_active')
                ->label('Active'),

            TextColumn::make('created_at')
                ->label('Created')
                ->dateTime()
                ->since()
                ->toggleable(isToggledHiddenByDefault: true),
        ]);
    }

    /**
     * Format pay day for display
     */
    protected static function formatPayDay(?int $payDay): string
    {
        if ($payDay === null) return '-';
        if ($payDay === 99) return 'Last day';
        if ($payDay === 98) return '1st next month';
        return $payDay . self::getOrdinalSuffix($payDay);
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
