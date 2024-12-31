<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\Department;
use App\Services\ExcelErrorImportService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('New Employee')
                ->label('New Employee')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(EmployeeResource::getUrl('create')),

            Action::make('Import Employees')
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

                    // Initialize the import service
                    $importService = new ExcelErrorImportService(Employee::class, function (array $row) {
                        // Department lookup logic
                        if (isset($row['external_department_id'])) {
                            $externalDepartmentId = str_pad((string) $row['external_department_id'], 3, '0', STR_PAD_LEFT);
                            $mappedDepartment = Department::where('external_department_id', $externalDepartmentId)->first();
                            $row['department_id'] = $mappedDepartment?->id ?? null;

                            if ($row['department_id'] === null) {
                                Log::warning("No department found for external_department_id: {$externalDepartmentId}");
                            } else {
                                Log::info("Mapped external_department_id {$externalDepartmentId} to department_id {$row['department_id']}");
                            }
                        } else {
                            Log::warning("external_department_id is missing in the input data: " . json_encode($row));
                        }

                        return $row; // Ensure the updated row is returned
                    });

                    try {
                        Excel::import($importService, $filePath);

                        if ($failedRecords = $importService->getFailedRecords()) {
                            // Export failed records if any errors occurred
                            return $importService->exportFailedRecords();
                        }

                        Notification::make()
                            ->title('Import Success')
                            ->body('Employees imported successfully!')
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

            Action::make('Export Employees')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    try {
                        return Excel::download(new DataExport(EmployeeResource::getModel()), 'employees.xlsx');
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
