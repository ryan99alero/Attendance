<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\FileUpload;
use Exception;
use App\Filament\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Imports\DataImport;
use App\Exports\DataExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        // Apply department filtering for managers
        $user = auth()->user();

        if ($user && $user->hasRole('manager') && !$user->hasRole('super_admin')) {
            $managedEmployeeIds = $user->getManagedEmployeeIds();

            if (!empty($managedEmployeeIds)) {
                $query->whereIn('employee_id', $managedEmployeeIds);
            } else {
                // If manager has no employees, show no records
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

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
                ->schema([
                    FileUpload::make('file')
                        ->label('Import File')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data) {
                    $fileName = $data['file'];
                    $filePath = storage_path("app/public/{$fileName}");

                    Log::info("Resolved file path for import: {$filePath}");

                    $importService = new DataImport(Attendance::class);

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
                    } catch (Exception $e) {
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
            Action::make('Export Attendances')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    try {
                        return Excel::download(new DataExport(AttendanceResource::getModel()), 'attendances.xlsx');
                    } catch (Exception $e) {
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
