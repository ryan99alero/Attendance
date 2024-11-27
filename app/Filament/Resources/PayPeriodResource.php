<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayPeriodResource\Pages;
use App\Models\PayPeriod;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;

class PayPeriodResource extends Resource
{
    protected static ?string $model = PayPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Define the form schema as needed
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Define table columns as needed
            ])
            ->filters([
                // Define filters as needed
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Define relations as needed
        ];
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
