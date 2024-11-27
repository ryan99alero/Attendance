<?php

namespace App\Filament\Resources\RoundingRuleResource\Pages;

use App\Filament\Resources\RoundingRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoundingRule extends EditRecord
{
    protected static string $resource = RoundingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
