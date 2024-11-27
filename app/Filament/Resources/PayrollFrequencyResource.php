<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollFrequencyResource\Pages;
use App\Models\PayrollFrequency;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;

class PayrollFrequencyResource extends Resource
{
    protected static ?string $model = PayrollFrequency::class;

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
                // Define table filters as needed
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
            'index' => Pages\ListPayrollFrequencies::route('/'),
            'create' => Pages\CreatePayrollFrequency::route('/create'),
            'edit' => Pages\EditPayrollFrequency::route('/{record}/edit'),
        ];
    }
}
