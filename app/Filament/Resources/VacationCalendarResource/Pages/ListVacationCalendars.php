<?php

namespace App\Filament\Resources\VacationCalendarResource\Pages;

use Filament\Forms\Components\FileUpload;
use Exception;
use App\Filament\Resources\VacationCalendarResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ListVacationCalendars extends ListRecords
{
    protected static string $resource = VacationCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Add a "Create" action button
            Action::make('create')
                ->label('New Vacation Entry')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(VacationCalendarResource::getUrl('create')),

            // Add an "Import" action button
            Action::make('Import Vacation Entries')
                ->label('Import')
                ->color('primary')
                ->icon('heroicon-o-arrow-up-on-square')
                ->schema([
                    FileUpload::make('file')
                        ->label('Import File')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data) {
                    $fileName = $data['file'];
                    $filePath = DataImport::resolveFilePath($fileName);

                    Log::info("Resolved file path for import: {$filePath}");

                    try {
                        Excel::import(
                            new class ('App\Models\VacationCalendar') extends DataImport {
                                protected function transformRow(array $row): array
                                {
                                    // Custom transformation logic if needed
                                    return $row;
                                }
                            },
                            $filePath
                        );

                        Notification::make()
                            ->title('Import Success')
                            ->body('Vacation entries imported successfully!')
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Log::error("Import failed: {$e->getMessage()}");

                        Notification::make()
                            ->title('Import Failed')
                            ->body("An error occurred during the import: {$e->getMessage()}")
                            ->danger()
                            ->send();
                    }
                }),

            // Add an "Export" action button
            Action::make('Export Vacation Entries')
                ->label('Export')
                ->color('warning')
                ->icon('heroicon-o-arrow-down-on-square')
                ->action(function () {
                    return Excel::download(new DataExport(VacationCalendarResource::getModel()), 'vacation_calendar.xlsx');
                }),
        ];
    }
}
