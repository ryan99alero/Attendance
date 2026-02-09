<?php

namespace App\Filament\Resources\OvertimeRuleResource\Pages;

use Filament\Forms\Components\FileUpload;
use Exception;
use App\Filament\Resources\OvertimeRuleResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ListOvertimeRules extends ListRecords
{
    protected static string $resource = OvertimeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Create Action
            Action::make('New Overtime Rule')
                ->label('New Overtime Rule')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(OvertimeRuleResource::getUrl('create')),

            // Import Action
            Action::make('Import Overtime Rules')
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
                            new class ('App\Models\OvertimeRule') extends DataImport {
                                protected function transformRow(array $row): array
                                {
                                    // Add transformations for overtime rules if necessary
                                    return $row;
                                }
                            },
                            $filePath
                        );

                        Notification::make()
                            ->title('Import Success')
                            ->body('Overtime rules imported successfully!')
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

            // Export Action
            Action::make('Export Overtime Rules')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    try {
                        return Excel::download(new DataExport(OvertimeRuleResource::getModel()), 'overtime_rules.xlsx');
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
