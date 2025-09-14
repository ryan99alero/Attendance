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
use Illuminate\Support\Facades\DB;
use App\Services\AttendanceProcessing\AttendanceProcessingService;
use Carbon\Carbon;


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

            TextColumn::make('attendance_issues_count')
                ->label('Attendance Issues')
                ->state(fn ($record) => $record->attendanceIssuesCount())
                ->sortable()
                ->url(fn ($record) => route('filament.admin.resources.attendances.index', [
                    'filter' => [
                        'is_migrated' => false,
                        'date_range' => [
                            'start' => $record->start_date->toDateString(),
                            'end' => $record->end_date->toDateString(),
                        ],
                    ],
                ]), true)
                ->color('danger'),

            TextColumn::make('punch_count')
                ->label('Punch Count')
                ->state(fn ($record) => $record->punchCount())
                ->sortable()
                ->color('success'),
        ])
            ->actions([
                // Process Time Button
                Action::make('process_time')
                    ->label('Process Time')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->extraAttributes(['data-action' => 'process_time'])
                    ->requiresConfirmation()
                    ->modalHeading('Process Time Records')
                    ->modalDescription('This will process attendance records for this pay period. This operation may take several minutes and will show progress notifications.')
                    ->modalSubmitActionLabel('Start Processing')
                    ->modalIcon('heroicon-o-arrow-down-on-square')
                    ->action(function ($record) {
                        set_time_limit(0); // Remove time limit for long operations

                        // Show initial processing notification
                        \Filament\Notifications\Notification::make()
                            ->info()
                            ->title('Processing Started')
                            ->body("Starting 9-step processing for Pay Period ID: {$record->id}")
                            ->duration(5000)
                            ->send();

                        $processingService = app(AttendanceProcessingService::class);

                        try {
                            $processingService->processAll($record);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('✅ Processing Complete')
                                ->body("Successfully processed all attendance records for Pay Period ID: {$record->id}")
                                ->duration(10000)
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('❌ Processing Failed')
                                ->body("Error processing Pay Period ID: {$record->id}: " . substr($e->getMessage(), 0, 200))
                                ->persistent()
                                ->send();

                            throw $e; // Re-throw for proper error handling
                        }
                    })
                    ->before(function () {
                        // This runs before the action, perfect for showing loading states
                        \Filament\Notifications\Notification::make()
                            ->info()
                            ->title('🚀 Preparing Processing')
                            ->body('Initializing attendance processing services...')
                            ->duration(3000)
                            ->send();
                    }),

                // Post Time Button
                Action::make('post_time')
                    ->label('Post Time')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Post Time Records')
                    ->modalDescription(fn ($record) => $record->attendanceIssuesCount() > 0
                        ? 'Warning: There are ' . $record->attendanceIssuesCount() . ' unresolved attendance issues. These must be resolved before posting.'
                        : 'This will finalize and archive all attendance records for this pay period. This action cannot be undone.'
                    )
                    ->modalSubmitActionLabel('Post Time')
                    ->disabled(fn ($record) => $record->attendanceIssuesCount() > 0)
                    ->action(function ($record) {
                        // Double-check for unresolved attendance issues
                        if ($record->attendanceIssuesCount() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Cannot Post Time')
                                ->body('There are ' . $record->attendanceIssuesCount() . ' unresolved attendance issues. Please resolve them first.')
                                ->send();
                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->info()
                            ->title('Posting Time Records')
                            ->body("Finalizing time records for Pay Period ID: {$record->id}...")
                            ->send();

                        try {
                            // Archive processed records and mark pay period as processed
                            $updatedRecords = DB::table('attendances')
                                ->whereBetween('punch_time', [
                                    \Carbon\Carbon::parse($record->start_date)->startOfDay(),
                                    \Carbon\Carbon::parse($record->end_date)->endOfDay(),
                                ])
                                ->update(['status' => 'Posted']);

                            $record->update(['is_posted' => true]);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Time Posted Successfully')
                                ->body("Finalized {$updatedRecords} attendance records for Pay Period ID: {$record->id}")
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Post Time Error')
                                ->body("Error posting time for Pay Period ID: {$record->id}: {$e->getMessage()}")
                                ->persistent()
                                ->send();
                        }
                    }),

                // View Punches Button
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
