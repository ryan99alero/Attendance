<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use App\Filament\Resources\RoundGroupResource\Pages\ListRoundGroups;
use App\Filament\Resources\RoundGroupResource\Pages\CreateRoundGroup;
use App\Filament\Resources\RoundGroupResource\Pages\EditRoundGroup;
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
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Round Groups';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?int $navigationSort = 10;

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
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('group_name')
                    ->label('Group Name')
                    ->sortable()
                    ->searchable(),
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
            // Define relationships if needed
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
