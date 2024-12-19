<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayPeriodResource\Pages;
use App\Models\PayPeriod;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class PayPeriodResource extends Resource
{
    protected static ?string $model = PayPeriod::class;
    protected static bool $shouldRegisterNavigation = false;
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

            TextColumn::make('attendanceIssuesCount')
                ->label('Attendance Issues')
                ->state(fn ($record) => $record->attendanceIssuesCount())
                ->sortable()
                ->url(fn ($record) => route('filament.admin.resources.attendances.index', [
                    'filter_ids' => $record->attendanceIssues()->pluck('id')->implode(',')
                ]), true) // Generate a URL with filter_ids query param
                ->color('danger'),

            TextColumn::make('punchCount')
                ->label('Punch Count')
                ->state(fn ($record) => $record->punchCount())
                ->sortable()
                ->color('success'),
        ])
            ->actions([
                // Button to trigger AttendanceProcessingService
                Action::make('fetch_attendance')
                    ->label('Fetch Attendance')
                    ->color('primary')
                    ->icon('heroicon-o-download')
                    ->action(function ($record) {
                        $count = $record->processAttendance(); // Calls the service through the model

                        return \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Attendance Fetched')
                            ->body("$count attendance records have been moved to the punches table.");
                    }),

                // Button to process vacation records
                Action::make('process_vacation')
                    ->label('Process Vacation')
                    ->color('success')
                    ->icon('heroicon-o-calendar')
                    ->action(function ($record) {
                        // Ensure start_date and end_date are strings
                        $startDate = $record->start_date->format('Y-m-d H:i:s'); // Full timestamp format
                        $endDate = $record->end_date->format('Y-m-d H:i:s'); // Full timestamp format

                        // Call the service
                        $service = app(\App\Services\AttendanceProcessing\VacationTimeProcessAttendanceService::class);
                        $service->processVacationDays($startDate, $endDate);

                        return \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Vacation Processed')
                            ->body("Vacation records have been successfully processed for Pay Period ID: {$record->id}");
                    }),

                // Button to view punches for the PayPeriod
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
