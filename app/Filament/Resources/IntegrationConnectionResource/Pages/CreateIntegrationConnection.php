<?php

namespace App\Filament\Resources\IntegrationConnectionResource\Pages;

use App\Filament\Resources\IntegrationConnectionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Crypt;

class CreateIntegrationConnection extends CreateRecord
{
    protected static string $resource = IntegrationConnectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Encrypt credentials before saving
        if (isset($data['credentials']) && is_array($data['credentials'])) {
            $data['auth_credentials'] = Crypt::encryptString(json_encode($data['credentials']));
            unset($data['credentials']);
        }

        $data['created_by'] = auth()->id();
        $data['webhook_token'] = bin2hex(random_bytes(32));

        return $data;
    }
}
