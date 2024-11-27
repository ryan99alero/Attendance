<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OvertimeRuleResource\Pages;
use App\Models\OvertimeRule;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

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
