<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use App\Models\Department;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
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
                    $filePath = DataImport::resolveFilePath($fileName);

                    Log::info("Resolved file path for import: {$filePath}");

                    try {
                        Excel::import(
                            new class ('App\Models\Employee') extends DataImport {
                                protected function transformRow(array $row): array
                                {
                                    if (isset($row['department_id'])) {
                                        $mappedDepartment = Department::where('external_department_id', $row['department_id'])->first();
                                        $row['department_id'] = $mappedDepartment?->id ?? null;
                                    }
                                    return $row;
                                }
                            },
                            $filePath
                        );

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
                    return Excel::download(new DataExport(EmployeeResource::getModel()), 'employees.xlsx');
                })
                ->icon('heroicon-o-download'),
        ];
    }
}
