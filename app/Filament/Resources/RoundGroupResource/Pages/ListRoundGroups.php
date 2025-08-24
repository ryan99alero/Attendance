<?php

namespace App\Filament\Resources\RoundGroupResource\Pages;

use Filament\Forms\Components\FileUpload;
use Exception;
use App\Filament\Resources\RoundGroupResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use App\Models\RoundGroup;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ListRoundGroups extends ListRecords
{
    protected static string $resource = RoundGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Add a "Create" action button
            Action::make('New Round Group')
                ->label('New Round Group')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(RoundGroupResource::getUrl('create')),

            // Add an "Import" action button
            Action::make('Import Round Groups')
                ->label('Import')
                ->color('primary')
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
                            new class ('App\Models\RoundGroup') extends DataImport {
                                protected function transformRow(array $row): array
                                {
                                    // Add any necessary data transformations for RoundGroup here
                                    return $row;
                                }
                            },
                            $filePath
                        );

                        Notification::make()
                            ->title('Import Success')
                            ->body('Round Groups imported successfully!')
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
                })
                ->icon('heroicon-o-arrow-up-tray'),

            // Add an "Export" action button
            Action::make('Export Round Groups')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    try {
                        return Excel::download(new DataExport(RoundGroupResource::getModel()), 'round_groups.xlsx');
                    } catch (Exception $e) {
                        Log::error("Export failed: {$e->getMessage()}");

                        Notification::make()
                            ->title('Export Failed')
                            ->body("An error occurred during the export: {$e->getMessage()}")
                            ->danger()
                            ->send();
                        return null;
                    }
                })
                ->icon('heroicon-o-arrow-down-tray'),
        ];
    }
}
