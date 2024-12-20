<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    /**
     * Define the header actions for the resource.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('New Employee')
                ->label('New Employee')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(EmployeeResource::getUrl('create')), // Redirect to the create form

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
                    Excel::import(new DataImport(EmployeeResource::getModel()), $data['file']);
                    $this->notify('success', 'Employees imported successfully!');
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
