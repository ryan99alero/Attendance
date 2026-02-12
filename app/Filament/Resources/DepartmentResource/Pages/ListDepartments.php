<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Exports\DataExport;
use App\Filament\Resources\DepartmentResource;
use App\Jobs\ProcessDataImportJob;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentResource::class;

    /**
     * Define the header actions for the resource.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('New Department')
                ->label('New Department')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(DepartmentResource::getUrl('create')),

            Action::make('Import Departments')
                ->label('Import')
                ->color('primary')
                ->schema([
                    FileUpload::make('file')
                        ->label('Import File')
                        ->required()
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data) {
                    $filePath = $data['file'];
                    $fileName = basename($filePath);

                    Log::info("Queuing department import job for file: {$filePath}");

                    // Create import record and dispatch job
                    $import = ProcessDataImportJob::createAndDispatch(
                        $filePath,
                        DepartmentResource::getModel(),
                        ProcessDataImportJob::PROCESSOR_DEPARTMENT,
                        auth()->id(),
                        $fileName
                    );

                    $rowInfo = $import->total_rows ? " ({$import->total_rows} rows)" : '';

                    Notification::make()
                        ->title('Import Queued')
                        ->body("Your department import{$rowInfo} has been queued. You will be notified when complete.")
                        ->info()
                        ->send();
                })
                ->icon('heroicon-o-arrow-up-on-square-stack'),

            Action::make('Export Departments')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    try {
                        return Excel::download(new DataExport(DepartmentResource::getModel()), 'departments.xlsx');
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
