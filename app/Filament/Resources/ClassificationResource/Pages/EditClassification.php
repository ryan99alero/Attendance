<?php

namespace App\Filament\Resources\ClassificationsResource\Pages;

use App\Filament\Resources\ClassificationsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClassification extends EditRecord
{
    protected static string $resource = ClassificationsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
