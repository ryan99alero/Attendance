<?php

namespace App\Filament\Resources\RoundGroupResource\Pages;

use App\Filament\Resources\RoundGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoundGroup extends EditRecord
{
    protected static string $resource = RoundGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
