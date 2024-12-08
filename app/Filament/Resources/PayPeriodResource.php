<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayPeriodResource\Pages;
use App\Models\PayPeriod;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action; // For custom actions
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class PayPeriodResource extends Resource
{
    protected static ?string $model = PayPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Pay Periods';

    public static function form(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('start_date')
                ->label('Start Date')
                ->required(),
            DatePicker::make('end_date')
                ->label('End Date')
                ->required(),
            Toggle::make('is_processed')
                ->label('Processed')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('start_date')
                ->label('Start Date')
                ->date(),
            TextColumn::make('end_date')
                ->label('End Date')
                ->date(),
            IconColumn::make('is_processed')
                ->label('Processed')
                ->boolean(),
        ])
            ->actions([
                // Existing "Fetch Attendance" button
                Action::make('fetch_attendance')
                    ->label('Fetch Attendance')
                    ->color('primary')
                    ->icon('heroicon-o-download')
                    ->action(function ($record) {
                        $count = $record->fetchAttendance();

                        return \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Attendance Fetched')
                            ->body("$count attendance records have been moved to the punches table.");
                    }),

                // New "View Punches" button
                Action::make('view_punches')
                    ->label('View Punches')
                    ->color('secondary')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.punches.index', ['pay_period_id' => $record->id])),
            ]);
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
