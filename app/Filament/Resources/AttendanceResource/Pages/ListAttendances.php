<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use App\Models\Attendance;
use App\Models\Employee;
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
                    $filePath = DataImport::resolveFilePath($fileName);

                    Log::info("Resolved file path for import: {$filePath}");

                    try {
                        Excel::import(
                            new class ('App\Models\Attendance') extends DataImport {
                                /**
                                 * Transform row data for Attendance imports.
                                 */
                                protected function transformRow(array $row): array
                                {
                                    // Attempt to map employee_external_id to employee_id
                                    if (isset($row['employee_external_id'])) {
                                        $employee = Employee::where('external_id', $row['employee_external_id'])->first();

                                        if ($employee) {
                                            $row['employee_id'] = $employee->id; // Set the mapped employee ID
                                            Log::info("Mapped external ID {$row['employee_external_id']} to employee ID {$employee->id}");
                                        } else {
                                            Log::warning("No employee found for external ID: {$row['employee_external_id']}. Row skipped.");
                                        }
                                    }

                                    return $row;
                                }

                                /**
                                 * Validate the row data before import.
                                 */
                                protected function validateRow(array $row): void
                                {
                                    if (empty($row['employee_id'])) {
                                        throw new \Exception("Employee ID is missing for external ID: {$row['employee_external_id']}");
                                    }

                                    if (empty($row['punch_time'])) {
                                        throw new \Exception("Punch time is missing or invalid for row: " . json_encode($row));
                                    }
                                }
                            },
                            $filePath
                        );

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
