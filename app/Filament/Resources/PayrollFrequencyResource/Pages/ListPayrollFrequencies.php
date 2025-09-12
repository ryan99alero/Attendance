<?php

namespace App\Filament\Resources\PayrollFrequencyResource\Pages;

use App\Filament\Resources\PayrollFrequencyResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ListPayrollFrequencies extends ListRecords
{
    protected static string $resource = PayrollFrequencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Create Action
            Action::make('New Payroll Frequency')
                ->label('New Payroll Frequency')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(PayrollFrequencyResource::getUrl('create')),

            // Import Action
            Action::make('Import Payroll Frequencies')
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
                            new class ('App\Models\PayrollFrequency') extends DataImport {
                                protected function transformRow(array $row): array
                                {
                                    // Add transformations for payroll frequencies if necessary
                                    return $row;
                                }
                            },
                            $filePath
                        );

                        Notification::make()
                            ->title('Import Success')
                            ->body('Payroll frequencies imported successfully!')
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

            // Export Action
            Action::make('Export Payroll Frequencies')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    try {
                        return Excel::download(new DataExport(PayrollFrequencyResource::getModel()), 'payroll_frequencies.xlsx');
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
