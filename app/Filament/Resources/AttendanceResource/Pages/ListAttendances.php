<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Imports\DataImport;
use App\Exports\DataExport;
use App\Services\ExcelErrorImportService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Add a "Create" action button
            Action::make('New Attendance')
                ->label('New Attendance')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(AttendanceResource::getUrl('create')),

            // Add an "Import" action button
            Action::make('Import Attendances')
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
                    $filePath = storage_path("app/public/{$fileName}");

                    Log::info("Resolved file path for import: {$filePath}");

                    $importService = new ExcelErrorImportService(Attendance::class);

                    try {
                        Excel::import($importService, $filePath);

                        if ($failedRecords = $importService->getFailedRecords()) {
                            // Export failed records if any errors occurred
                            return $importService->exportFailedRecords();
                        }

                        Notification::make()
                            ->title('Import Success')
                            ->body('Attendances imported successfully!')
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
                ->icon('heroicon-o-upload'),

            // Add an "Export" action button
            Action::make('Export Attendances')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    try {
                        return Excel::download(new DataExport(AttendanceResource::getModel()), 'attendances.xlsx');
                    } catch (\Exception $e) {
                        Log::error("Export failed: {$e->getMessage()}");

                        Notification::make()
                            ->title('Export Failed')
                            ->body("An error occurred during the export: {$e->getMessage()}")
                            ->danger()
                            ->send();
                    }
                })
                ->icon('heroicon-o-download'),
        ];
    }
}
