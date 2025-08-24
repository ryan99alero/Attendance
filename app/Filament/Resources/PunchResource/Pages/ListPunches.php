<?php

namespace App\Filament\Resources\PunchResource\Pages;

use Filament\Forms\Components\FileUpload;
use App\Filament\Resources\PunchResource;
use App\Imports\DataImport;
use App\Exports\DataExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListPunches extends ListRecords
{
    protected static string $resource = PunchResource::class;

    /**
     * Define the header actions for the resource.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('New Punch')
                ->label('New Punch')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(PunchResource::getUrl('create')), // Redirect to the create form

            Action::make('Import Punchs')
                ->label('Import')
                ->color('primary')
                ->schema([
                    FileUpload::make('file')
                        ->label('Import File')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data) {
                    Excel::import(new DataImport(PunchResource::getModel()), $data['file']);
                    $this->notify('success', 'Punchs imported successfully!');
                })
                ->icon('heroicon-o-arrow-up-tray'),

            Action::make('Export Punchs')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    return Excel::download(new DataExport(PunchResource::getModel()), 'punch.xlsx');
                })
                ->icon('heroicon-o-arrow-down-tray'),
        ];
    }
}
