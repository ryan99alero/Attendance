<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayPeriodResource\Pages;
use App\Models\PayPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Forms\Form;

class PayPeriodResource extends Resource
{
    protected static ?string $model = PayPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Pay Periods';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('frequency')
                ->label('Frequency')
                ->options([
                    'weekly' => 'Weekly',
                    'bi-weekly' => 'Bi-Weekly',
                    'monthly' => 'Monthly',
                ])
                ->required(),
            DatePicker::make('start_date')
                ->label('Start Date')
                ->required(),
            DatePicker::make('end_date')
                ->label('End Date')
                ->required(),
            Toggle::make('is_processed')
                ->label('Processed')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('frequency')
                ->label('Frequency')
                ->sortable(),
            TextColumn::make('start_date')
                ->label('Start Date')
                ->date(),
            TextColumn::make('end_date')
                ->label('End Date')
                ->date(),
            IconColumn::make('is_processed')
                ->label('Processed')
                ->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayPeriods::route('/'),
            'create' => Pages\CreatePayPeriod::route('/create'),
            'edit' => Pages\EditPayPeriod::route('/{record}/edit'),
        ];
    }
}
