<?php

namespace App\Filament\Resources\PunchTypeResource\Pages;

use App\Filament\Resources\PunchTypeResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListPunchTypes extends ListRecords
{
    protected static string $resource = PunchTypeResource::class;

    /**
     * Define the header actions for the resource.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('New Punch Type')
                ->label('New Punch Type')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(PunchTypeResource::getUrl('create')), // Redirect to the create form

            Action::make('Import Punch Types')
                ->label('Import')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label('Import File')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data) {
                    Excel::import(new DataImport(PunchTypeResource::getModel()), $data['file']);
                    $this->notify('success', 'Punch types imported successfully!');
                })
                ->icon('heroicon-o-arrow-up-on-square-stack'),

            Action::make('Export Punch Types')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    return Excel::download(new DataExport(PunchTypeResource::getModel()), 'punch_types.xlsx');
                })
                ->icon('heroicon-o-arrow-down-on-square'),
        ];
    }
}
