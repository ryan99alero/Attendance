<?php

namespace App\Filament\Resources\ClassificationResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ClassificationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClassification extends EditRecord
{
    protected static string $resource = ClassificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
