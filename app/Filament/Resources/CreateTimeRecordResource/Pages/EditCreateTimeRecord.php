<?php

namespace App\Filament\Resources\CreateTimeRecordResource\Pages;

use App\Filament\Resources\CreateTimeRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCreateTimeRecord extends EditRecord
{
    protected static string $resource = CreateTimeRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
