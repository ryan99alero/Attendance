<?php

namespace App\Filament\Resources\OvertimeRuleResource\Pages;

use App\Filament\Resources\OvertimeRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOvertimeRule extends EditRecord
{
    protected static string $resource = OvertimeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
