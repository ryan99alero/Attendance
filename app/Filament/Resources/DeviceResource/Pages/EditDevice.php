<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Configuration fields that should trigger version increment
        $configFields = ['device_name', 'display_name', 'timezone', 'ntp_server', 'registration_status'];

        // Check if any configuration fields changed
        $configChanged = false;
        foreach ($configFields as $field) {
            if (array_key_exists($field, $data) &&
                $data[$field] != $this->record->getOriginal($field)) {
                $configChanged = true;
                break;
            }
        }

        // Increment config version and set updated timestamp if config changed
        if ($configChanged) {
            $data['config_version'] = ($this->record->config_version ?? 0) + 1;
            $data['config_updated_at'] = now();
        }

        return $data;
    }
}
