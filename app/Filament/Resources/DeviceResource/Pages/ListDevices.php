<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use App\Models\Device;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Add a "Create" action button
            Action::make('New Device')
                ->label('New Device')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(DeviceResource::getUrl('create')),

            // Add an "Import" action button
            Action::make('Import Devices')
                ->label('Import')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('file')
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
                            new class ('App\Models\Device') extends DataImport {
                                protected function transformRow(array $row): array
                                {
                                    // Add any necessary data transformations for Device here
                                    return $row;
                                }
                            },
                            $filePath
                        );

                        Notification::make()
                            ->title('Import Success')
                            ->body('Devices imported successfully!')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error("Import failed: {$e->getMessage()}");

                        Notification::make()
                            ->title('Import Failed')
                            ->body("An error occurred during the import: {$e->getMessage()}")
                            ->danger()
                            ->send();
                    }
                })
                ->icon('heroicon-o-arrow-up-on-square-stack'),

            // Add an "Export" action button
            Action::make('Export Devices')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    try {
                        return Excel::download(new DataExport(DeviceResource::getModel()), 'devices.xlsx');
                    } catch (\Exception $e) {
                        Log::error("Export failed: {$e->getMessage()}");

                        Notification::make()
                            ->title('Export Failed')
                            ->body("An error occurred during the export: {$e->getMessage()}")
                            ->danger()
                            ->send();
                    }
                })
                ->icon('heroicon-o-arrow-down-on-square'),
        ];
    }
}
