<?php

namespace App\Filament\Resources\VacationBalanceResource\Pages;

use Filament\Forms\Components\FileUpload;
use Exception;
use App\Filament\Resources\VacationBalanceResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ListVacationBalances extends ListRecords
{
    protected static string $resource = VacationBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Add a "Create" action button
            Action::make('create')
                ->label('New Vacation Balance')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(VacationBalanceResource::getUrl('create')),

            // Add an "Import" action button
            Action::make('Import Vacation Balances')
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
                    $fileName = $data['file'];
                    $filePath = DataImport::resolveFilePath($fileName);

                    Log::info("Resolved file path for import: {$filePath}");

                    try {
                        Excel::import(
                            new class ('App\Models\VacationBalance') extends DataImport {
                                protected function transformRow(array $row): array
                                {
                                    // Custom transformation logic for vacation balances
                                    return $row;
                                }
                            },
                            $filePath
                        );

                        Notification::make()
                            ->title('Import Success')
                            ->body('Vacation balances imported successfully!')
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

            // Add an "Export" action button
            Action::make('Export Vacation Balances')
                ->label('Export')
                ->color('warning')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    return Excel::download(new DataExport(VacationBalanceResource::getModel()), 'vacation_balances.xlsx');
                }),
        ];
    }
}
