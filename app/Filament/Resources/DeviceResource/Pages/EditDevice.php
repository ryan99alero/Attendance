<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reboot')
                ->label('Reboot Device')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reboot Time Clock')
                ->modalDescription('Are you sure you want to reboot this device? The device will restart on its next health check (within 30 seconds).')
                ->modalSubmitActionLabel('Yes, reboot device')
                ->visible(fn () => $this->record->device_type === 'esp32_timeclock' && $this->record->registration_status === 'approved')
                ->action(function () {
                    $this->record->update(['reboot_requested' => true]);

                    Notification::make()
                        ->title('Reboot requested')
                        ->body('The device will reboot on its next health check (within 30 seconds).')
                        ->success()
                        ->send();
                }),
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
