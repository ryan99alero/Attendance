<?php

namespace App\Filament\Resources\SystemTasks\Pages;

use App\Filament\Resources\SystemTasks\SystemTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSystemTasks extends ManageRecords
{
    protected static string $resource = SystemTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
