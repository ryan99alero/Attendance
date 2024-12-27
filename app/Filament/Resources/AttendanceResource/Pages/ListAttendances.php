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

                    $import = new DataImport(Attendance::class);

                    try {
                        Excel::import($import, $filePath);

                        // Check for failed records
                        if (!empty($import->getFailedRecordsWithErrors())) {
                            $failedRecords = $import->getFailedRecordsWithErrors();

                            // Add error messages and context to the original rows
                            $updatedRows = collect(Excel::toArray([], $filePath)[0])
                                ->map(function ($row, $index) use ($failedRecords) {
                                    // Adjust index to account for the header row
                                    $failedRecord = collect($failedRecords)->firstWhere('row', $index - 1);

                                    return array_merge($row, [
                                        'error' => $failedRecord['error'] ?? null,
                                        'employee_name' => $failedRecord['employee_name'] ?? null,
                                        'department_name' => $failedRecord['department_name'] ?? null,
                                    ]);
                                });

                            // Return the updated file with errors
                            return Excel::download(new class($updatedRows) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
                                private $data;
                                public function __construct($data) {
                                    $this->data = $data;
                                }
                                public function collection() {
                                    return collect($this->data);
                                }
                                public function headings(): array {
                                    return array_keys($this->data->first());
                                }
                            }, 'attendance_import_errors.xlsx');
                        }

                        // Notify user of success if no errors
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
