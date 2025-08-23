<?php

namespace App\Filament\Resources;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ClassificationResource\Pages\ListClassifications;
use App\Filament\Resources\ClassificationResource\Pages\CreateClassification;
use App\Filament\Resources\ClassificationResource\Pages\EditClassification;
use App\Filament\Resources\ClassificationResource\Pages;
use App\Models\Classification;
use App\Filament\Resources\ClassificationResource\Schemas\ClassificationForm;
use App\Filament\Resources\ClassificationResource\Tables\ClassificationTable;
use Filament\Resources\Resource;

class ClassificationResource extends Resource
{
    protected static ?string $model = Classification::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $navigationLabel = 'Classifications';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;

    public static function form($schema)
    {
        return ClassificationForm::configure($schema);
    }

    public static function table($table)
    {
        return ClassificationTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getRecordRouteBindingEloquentQuery(Builder $query): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery($query)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
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
