<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Exception;
use App\Filament\Resources\PayPeriodResource\Pages\ListPayPeriods;
use App\Filament\Resources\PayPeriodResource\Pages\CreatePayPeriod;
use App\Filament\Resources\PayPeriodResource\Pages\EditPayPeriod;
use App\Filament\Resources\PayPeriodResource\Pages;
use App\Models\PayPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
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
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Pay Periods';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
            ->recordActions([
                // Process Time Button
                Action::make('process_time')
                    ->label('Process Time')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($record) {
                        $processingService = app(AttendanceProcessingService::class);

                        try {
                            $processingService->processAll($record);

                            return Notification::make()
                                ->success()
                                ->title('Time Processed')
                                ->body("Time records have been successfully processed for Pay Period ID: {$record->id}");
                        } catch (Exception $e) {
                            return Notification::make()
                                ->danger()
                                ->title('Processing Error')
                                ->body("An error occurred while processing Pay Period ID: {$record->id}. Error: {$e->getMessage()}");
                        }
                    }),

                // Post Time Button
                Action::make('post_time')
                    ->label('Post Time')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->action(function ($record) {
                        // Check for unresolved attendance issues
                        if ($record->attendanceIssuesCount() > 0) {
                            return Notification::make()
                                ->danger()
                                ->title('Cannot Post Time')
                                ->body('There are unresolved attendance issues for this pay period. Please resolve them before posting time.')
                                ->send();
                        }

                        // Archive processed records and mark pay period as processed
                        DB::table('attendances')
                            ->whereBetween('punch_time', [
                                Carbon::parse($record->start_date)->startOfDay(),
                                Carbon::parse($record->end_date)->endOfDay(),
                            ])
                            ->update(['status' => 'Posted']);

                        $record->update(['is_posted' => true]);

                        return Notification::make()
                            ->success()
                            ->title('Time Posted')
                            ->body("Time records for Pay Period ID: {$record->id} have been finalized and archived.")
                            ->send();
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
            'index' => ListPayPeriods::route('/'),
            'create' => CreatePayPeriod::route('/create'),
            'edit' => EditPayPeriod::route('/{record}/edit'),
        ];
    }
}
