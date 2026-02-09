<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use App\Filament\Resources\RoundGroupResource\Pages\ListRoundGroups;
use App\Filament\Resources\RoundGroupResource\Pages\CreateRoundGroup;
use App\Filament\Resources\RoundGroupResource\Pages\EditRoundGroup;
use App\Filament\Resources\RoundGroupResource\RelationManagers\RoundingRulesRelationManager;
use UnitEnum;
use BackedEnum;

use App\Filament\Resources\RoundGroupResource\Pages;
use App\Models\RoundGroup;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoundGroupResource extends Resource
{
    protected static ?string $model = RoundGroup::class;

    // Navigation Configuration
    protected static string | \UnitEnum | null $navigationGroup = 'Payroll & Overtime';
    protected static ?string $navigationLabel = 'Rounding Rules';
    protected static ?string $modelLabel = 'Rounding Group';
    protected static ?string $pluralModelLabel = 'Rounding Groups';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calculator';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('group_name')
                    ->label('Group Name')
                    ->required()
                    ->unique('round_groups', 'group_name', fn ($record) => $record), // Ensure uniqueness during updates
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group_name')
                    ->label('Rounding Group')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('rounding_rules_count')
                    ->label('Rules')
                    ->counts('roundingRules')
                    ->sortable(),
                TextColumn::make('employees_count')
                    ->label('Employees')
                    ->counts('employees')
                    ->sortable(),
            ])
            ->filters([
                // Add filters if needed
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RoundingRulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoundGroups::route('/'),
            'create' => CreateRoundGroup::route('/create'),
            'edit' => EditRoundGroup::route('/{record}/edit'),
        ];
    }
}
