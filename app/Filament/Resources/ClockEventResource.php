<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClockEventResource\Pages;
use App\Filament\Resources\ClockEventResource\RelationManagers;
use App\Models\ClockEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClockEventResource extends Resource
{
    protected static ?string $model = ClockEvent::class;

    // Navigation Configuration
    protected static ?string $navigationGroup = 'Punch & Attendance';
    protected static ?string $navigationLabel = 'Clock Events';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = 15;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClockEvents::route('/'),
            'create' => Pages\CreateClockEvent::route('/create'),
            'edit' => Pages\EditClockEvent::route('/{record}/edit'),
        ];
    }
}
