<?php

namespace App\Filament\Resources;

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
    protected static ?string $navigationGroup = 'Payroll & Overtime';
    protected static ?string $navigationLabel = 'Round Groups';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 30;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('group_name')
                    ->label('Group Name')
                    ->required()
                    ->unique('round_groups', 'group_name', fn ($record) => $record), // Ensure uniqueness during updates
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
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
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListRoundGroups::route('/'),
            'create' => Pages\CreateRoundGroup::route('/create'),
            'edit' => Pages\EditRoundGroup::route('/{record}/edit'),
        ];
    }
}
