<?php

namespace App\Filament\Resources\CredentialResource\Pages;

use App\Filament\Exports\CredentialExporter;
use App\Filament\Imports\CredentialImporter;
use App\Filament\Resources\CredentialResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListCredentials extends ListRecords
{
    protected static string $resource = CredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(CredentialImporter::class),
            ExportAction::make()
                ->exporter(CredentialExporter::class),
        ];
    }
}
