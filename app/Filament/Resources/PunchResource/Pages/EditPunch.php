<?php

namespace App\Filament\Resources\PunchResource\Pages;

use App\Filament\Resources\PunchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPunch extends EditRecord
{
    protected static string $resource = PunchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
