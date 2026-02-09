<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ClassificationResource\Pages\ListClassifications;
use App\Filament\Resources\ClassificationResource\Pages\CreateClassification;
use App\Filament\Resources\ClassificationResource\Pages\EditClassification;
use UnitEnum;
use BackedEnum;

use App\Filament\Resources\ClassificationResource\Pages;
use App\Models\Classification;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\Resource;

class ClassificationResource extends Resource
{
    protected static ?string $model = Classification::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Classifications';
    protected static string | \UnitEnum | null $navigationGroup = 'System & Hardware';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required(),
                Textarea::make('description')
                    ->label('Description')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->date(),
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->date(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassifications::route('/'),
            'create' => CreateClassification::route('/create'),
            'edit' => EditClassification::route('/{record}/edit'),
        ];
    }
}
