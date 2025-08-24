<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Forms\Components\FileUpload;
use Exception;
use App\Filament\Resources\UserResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Create Action
            Action::make('New User')
                ->label('New User')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(UserResource::getUrl('create')),

            // Import Action
            Action::make('Import Users')
                ->label('Import')
                ->color('primary')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    FileUpload::make('file')
                        ->label('Import File')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data) {
                    try {
                        $fileName = $data['file'];
                        $filePath = DataImport::resolveFilePath($fileName);

                        Log::info("Resolved file path for import: {$filePath}");

                        Excel::import(new DataImport(UserResource::getModel()), $filePath);

                        Notification::make()
                            ->title('Import Success')
                            ->body('Users imported successfully!')
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

            // Export Action
            Action::make('Export Users')
                ->label('Export')
                ->color('warning')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    try {
                        return Excel::download(new DataExport(UserResource::getModel()), 'users.xlsx');
                    } catch (Exception $e) {
                        Log::error("Export failed: {$e->getMessage()}");

                        Notification::make()
                            ->title('Export Failed')
                            ->body("An error occurred during the export: {$e->getMessage()}")
                            ->danger()
                            ->send();
                        return null;
                    }
                }),
        ];
    }
}
