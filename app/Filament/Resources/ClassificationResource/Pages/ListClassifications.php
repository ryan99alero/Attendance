<?php

namespace App\Filament\Resources\ClassificationsResource\Pages;

use App\Filament\Resources\ClassificationsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClassifications extends ListRecords
{
    protected static string $resource = ClassificationsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
