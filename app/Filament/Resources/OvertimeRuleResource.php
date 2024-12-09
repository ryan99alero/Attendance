<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OvertimeRuleResource\Pages;
use App\Models\OvertimeRule;
use App\Models\Shift;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;

class OvertimeRuleResource extends Resource
{
    protected static ?string $model = OvertimeRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Overtime Rules';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('rule_name')
                ->label('Rule Name')
                ->required(),
            TextInput::make('hours_threshold')
                ->label('Hours Threshold')
                ->numeric()
                ->required(),
            TextInput::make('multiplier')
                ->label('Multiplier')
                ->numeric()
                ->required(),
            Select::make('shift_id')
                ->label('Shift')
                ->options(Shift::all()->pluck('shift_name', 'id'))
                ->searchable()
                ->nullable(),
            TextInput::make('consecutive_days_threshold')
                ->label('Consecutive Days Threshold')
                ->numeric()
                ->nullable()
                ->hint('Number of consecutive days required to trigger the rule.'),
            Toggle::make('applies_on_weekends')
                ->label('Applies on Weekends')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('rule_name')
                ->label('Rule Name'),
            TextColumn::make('hours_threshold')
                ->label('Hours Threshold'),
            TextColumn::make('multiplier')
                ->label('Multiplier'),
            TextColumn::make('shift.shift_name')
                ->label('Shift')
                ->sortable(),
            TextColumn::make('consecutive_days_threshold')
                ->label('Consecutive Days Threshold'),
            BooleanColumn::make('applies_on_weekends')
                ->label('Applies on Weekends'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOvertimeRules::route('/'),
            'create' => Pages\CreateOvertimeRule::route('/create'),
            'edit' => Pages\EditOvertimeRule::route('/{record}/edit'),
        ];
    }
}
