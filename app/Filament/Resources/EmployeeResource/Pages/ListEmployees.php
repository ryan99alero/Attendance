<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Exports\DataExport;
use App\Filament\Resources\EmployeeResource;
use App\Jobs\ProcessDataImportJob;
use App\Models\Employee;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

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

                    Log::info("Queuing import job for file: {$filePath}");

                    // Create import record and dispatch job
                    $import = ProcessDataImportJob::createAndDispatch(
                        $filePath,
                        Employee::class,
                        ProcessDataImportJob::PROCESSOR_EMPLOYEE,
                        auth()->id(),
                        $fileName
                    );

                    $rowInfo = $import->total_rows ? " ({$import->total_rows} rows)" : '';

                    Notification::make()
                        ->title('Import Queued')
                        ->body("Your employee import{$rowInfo} has been queued. You will be notified when complete.")
                        ->info()
                        ->send();
                })
                ->icon('heroicon-o-arrow-up-on-square-stack'),

            Action::make('Export Employees')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    try {
                        return Excel::download(new DataExport(EmployeeResource::getModel()), 'employees.xlsx');
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

            Action::make('toggle_active')
                ->label(fn () => $this->tableFilters['hide_inactive']['isActive'] ?? true ? 'Show All' : 'Hide Inactive')
                ->color('gray')
                ->icon(fn () => $this->tableFilters['hide_inactive']['isActive'] ?? true ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
                ->action(function () {
                    $this->tableFilters['hide_inactive']['isActive'] = ! ($this->tableFilters['hide_inactive']['isActive'] ?? true);
                }),
        ];
    }

    public function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if ($this->tableFilters['hide_inactive']['isActive'] ?? true) {
            $query->where('is_active', true);
        }

        return $query;
    }
}
