<?php

namespace App\Filament\Resources\ClockEventResource\Pages;

use App\Filament\Exports\ClockEventExporter;
use App\Filament\Imports\ClockEventImporter;
use App\Filament\Resources\ClockEventResource;
use App\Jobs\ProcessClockEventsJob;
use App\Models\ClockEvent;
use App\Services\ClockEventProcessing\ClockEventProcessingService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\TextSize;

class ListClockEvents extends ListRecords
{
    protected static string $resource = ClockEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(ClockEventImporter::class),
            ExportAction::make()
                ->exporter(ClockEventExporter::class),

            Action::make('process_batch')
                ->label('Process Events')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Process ClockEvents')
                ->modalDescription(function () {
                    $count = ClockEvent::readyForProcessing()->count();

                    return "This will queue a background job to process {$count} clock events into attendance records. You will be notified when complete.";
                })
                ->modalSubmitActionLabel('Queue Processing Job')
                ->action(function () {
                    $pendingCount = ClockEvent::readyForProcessing()->count();

                    if ($pendingCount === 0) {
                        Notification::make()
                            ->title('No Events to Process')
                            ->body('All ClockEvents have already been processed.')
                            ->info()
                            ->send();

                        return;
                    }

                    // Dispatch the job
                    ProcessClockEventsJob::dispatch(auth()->id());

                    Notification::make()
                        ->title('Processing Job Queued')
                        ->body("A background job has been queued to process {$pendingCount} clock events. You will be notified when complete.")
                        ->success()
                        ->send();
                }),

            Action::make('processing_stats')
                ->label('Stats')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->modalHeading('ClockEvent Processing Statistics')
                ->schema(function (ClockEventProcessingService $service) {
                    $stats = $service->getProcessingStats();

                    return [
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('total_events')
                                    ->label('Total Events')
                                    ->icon('heroicon-o-chart-bar')
                                    ->iconPosition(IconPosition::Before)
                                    ->color('primary')
                                    ->size(TextSize::Large)
                                    ->state(number_format($stats['total_events'])),

                                TextEntry::make('processed_events')
                                    ->label('Processed Events')
                                    ->icon('heroicon-o-check-circle')
                                    ->iconPosition(IconPosition::Before)
                                    ->color('success')
                                    ->size(TextSize::Large)
                                    ->state(number_format($stats['processed_events'])),

                                TextEntry::make('unprocessed_events')
                                    ->label('Unprocessed Events')
                                    ->icon('heroicon-o-clock')
                                    ->iconPosition(IconPosition::Before)
                                    ->color('warning')
                                    ->size(TextSize::Large)
                                    ->state(number_format($stats['unprocessed_events'])),

                                TextEntry::make('ready_for_processing')
                                    ->label('Ready for Processing')
                                    ->icon('heroicon-o-clipboard-document-check')
                                    ->iconPosition(IconPosition::Before)
                                    ->color('info')
                                    ->size(TextSize::Large)
                                    ->state(number_format($stats['ready_for_processing'])),
                            ]),

                        TextEntry::make('events_with_errors')
                            ->label('Events with Errors')
                            ->icon('heroicon-o-exclamation-circle')
                            ->iconPosition(IconPosition::Before)
                            ->color('danger')
                            ->size(TextSize::Large)
                            ->state(number_format($stats['events_with_errors']))
                            ->visible($stats['events_with_errors'] > 0)
                            ->helperText('Use the artisan command `clock-events:process --retry-failed` to retry these events.'),

                    ];
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }
}
