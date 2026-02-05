<?php

namespace App\Filament\Resources\IntegrationConnectionResource\Pages;

use App\Filament\Resources\IntegrationConnectionResource;
use App\Services\Integrations\PaceApiClient;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;

class EditIntegrationConnection extends EditRecord
{
    protected static string $resource = IntegrationConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_connection')
                ->label('Test Connection')
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action(function () {
                    $driver = $this->record->driver;

                    if ($driver === 'pace') {
                        $client = new PaceApiClient($this->record);
                        $result = $client->testConnection();
                    } else {
                        // Generic test: just try an HTTP GET to the base URL
                        try {
                            $response = \Illuminate\Support\Facades\Http::timeout($this->record->timeout_seconds)
                                ->get($this->record->base_url);

                            $this->record->markConnected();
                            $result = [
                                'success' => true,
                                'message' => 'Connection successful (HTTP ' . $response->status() . ')',
                            ];
                        } catch (\Exception $e) {
                            $this->record->markError($e->getMessage());
                            $result = [
                                'success' => false,
                                'message' => $e->getMessage(),
                            ];
                        }
                    }

                    if ($result['success']) {
                        $body = $result['message'];
                        if (!empty($result['version'])) {
                            $body .= "\nPace Version: " . $result['version'];
                        }

                        Notification::make()
                            ->title('Connection Successful')
                            ->body($body)
                            ->success()
                            ->duration(10000)
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Connection Failed')
                            ->body($result['message'])
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\Action::make('discover_objects')
                ->label('Discover Objects')
                ->icon('heroicon-o-magnifying-glass')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Discover API Objects')
                ->modalDescription('This will query the API to discover available objects and their fields. Existing object definitions will be updated.')
                ->action(function () {
                    // TODO: Implement object discovery
                    Notification::make()
                        ->title('Object Discovery')
                        ->body('Object discovery not yet implemented. Coming soon.')
                        ->warning()
                        ->send();
                })
                ->visible(fn () => $this->record->driver === 'pace'),

            Actions\Action::make('force_sync')
                ->label('Force Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Force Sync Now')
                ->modalDescription('This will immediately run a full sync for this connection. Continue?')
                ->action(function () {
                    try {
                        $exitCode = Artisan::call('pace:sync-employees', [
                            '--connection' => $this->record->id,
                        ]);

                        $this->record->markSynced();

                        if ($exitCode === 0) {
                            Notification::make()
                                ->title('Sync Completed')
                                ->body('Employee sync finished successfully.')
                                ->success()
                                ->duration(10000)
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Sync Completed with Errors')
                                ->body('Sync ran but returned errors. Check sync logs for details.')
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                })
                ->visible(fn () => $this->record->driver === 'pace'),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Decrypt credentials for editing
        if (!empty($data['auth_credentials'])) {
            try {
                $data['credentials'] = json_decode(Crypt::decryptString($data['auth_credentials']), true);
            } catch (\Exception $e) {
                $data['credentials'] = [];
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Encrypt credentials before saving
        if (isset($data['credentials']) && is_array($data['credentials'])) {
            // Filter out empty values but keep the structure
            $credentials = array_filter($data['credentials'], fn($value) => $value !== null && $value !== '');
            $data['auth_credentials'] = Crypt::encryptString(json_encode($credentials));
            unset($data['credentials']);
        }

        $data['updated_by'] = auth()->id();

        return $data;
    }
}
