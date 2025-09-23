<?php

namespace App\Filament\Resources\ClockEventResource\Pages;

use App\Filament\Resources\ClockEventResource;
use App\Services\ClockEventProcessing\ClockEventProcessingService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListClockEvents extends ListRecords
{
    protected static string $resource = ClockEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('process_batch')
                ->label('Process Events')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Process ClockEvents')
                ->modalDescription('Convert unprocessed ClockEvents into Attendance records for further processing.')
                ->modalSubmitActionLabel('Process Now')
                ->action(function (ClockEventProcessingService $service) {
                    $stats = $service->getProcessingStats();

                    if ($stats['ready_for_processing'] === 0) {
                        Notification::make()
                            ->title('No Events to Process')
                            ->body('All ClockEvents have already been processed.')
                            ->info()
                            ->send();
                        return;
                    }

                    $result = $service->processUnprocessedEvents(100);

                    Notification::make()
                        ->title('Batch Processing Complete')
                        ->body("Successfully processed {$result['processed']} events into attendance records." .
                               ($result['errors'] > 0 ? " {$result['errors']} events had errors." : ""))
                        ->success()
                        ->send();

                    // Refresh the page to show updated data
                    $this->redirect(request()->header('Referer'));
                }),

            Actions\Action::make('processing_stats')
                ->label('Stats')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->modalHeading('ClockEvent Processing Statistics')
                ->modalContent(function (ClockEventProcessingService $service) {
                    $stats = $service->getProcessingStats();

                    return view('filament.resources.clock-event-resource.stats', compact('stats'));
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

            Actions\CreateAction::make(),
        ];
    }
}
