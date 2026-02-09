<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use Filament\Forms\Components\FileUpload;
use Exception;
use App\Filament\Resources\DepartmentResource;
use Illuminate\Support\Facades\Log;
use App\Imports\DataImport;
use App\Exports\DataExport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentResource::class;

    /**
     * Define the header actions for the resource.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('New Department')
                ->label('New Department')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(DepartmentResource::getUrl('create')), // Redirect to the create form

            Action::make('Import Departments')
                ->label('Import')
                ->color('primary')
                ->schema([
                    FileUpload::make('file')
                        ->label('Import File')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data) {
                    try {
                        Log::info('TACO: Starting the import process.');

                        // Store file to public disk and get the path
                        $fileName = $data['file'];
                        Log::info("TACO1: File received: {$fileName}");

                        $filePath = Storage::disk('public')->path($fileName);
                        Log::info("TACO2: Resolved file path: {$filePath}");

                        // Check if file exists
                        if (!Storage::disk('public')->exists($fileName)) {
                            Log::error("TACO3: File does not exist in public storage: {$fileName}");
                            throw new Exception('File does not exist.');
                        }

                        // Perform the import
                        Log::info('TACO4: Starting Excel import process.');
                        Excel::import(new DataImport(DepartmentResource::getModel()), $filePath);
                        Log::info('TACO5: Excel import completed.');

                        Notification::make()
                            ->title('Success')
                            ->body('Departments imported successfully!')
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Log::error('TACO6: Import failed: ' . $e->getMessage());

                        Notification::make()
                            ->title('Error')
                            ->body('Import failed: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->icon('heroicon-o-arrow-up-on-square-stack'),

            Action::make('Export Departments')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    Log::info('TACO7: Starting the export process.');

                    return Excel::download(new DataExport(DepartmentResource::getModel()), 'departments.xlsx');
                })
                ->icon('heroicon-o-arrow-down-on-square'),
        ];
    }
}
