<?php

namespace App\Filament\Resources\IntegrationConnectionResource\Pages;

use App\Filament\Resources\IntegrationConnectionResource;
use App\Services\Integrations\IntegrationSyncEngine;
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
                ->modalDescription('This will query the API to validate configured objects and their field mappings against the live API.')
                ->action(function () {
                    $objects = $this->record->objects()->get();

                    if ($objects->isEmpty()) {
                        Notification::make()
                            ->title('No Objects Configured')
                            ->body('Add integration objects first, then use Discover to validate them against the API.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $client = new PaceApiClient($this->record);
                    $engine = new IntegrationSyncEngine($client);

                    $results = [];
                    foreach ($objects as $object) {
                        $results[] = $engine->discoverObject($object);
                    }

                    $statusLines = [];
                    $allSuccess = true;
                    foreach ($results as $r) {
                        if ($r['success']) {
                            $statusLines[] = "{$r['object_name']}: {$r['fields_found']} fields with data, {$r['fields_null']} empty ({$r['total_records']} total records)";
                        } else {
                            $statusLines[] = "{$r['object_name']}: FAILED — {$r['error']}";
                            $allSuccess = false;
                        }
                    }

                    $body = implode("\n", $statusLines);

                    if ($allSuccess) {
                        Notification::make()
                            ->title('Discovery Complete')
                            ->body($body)
                            ->success()
                            ->duration(15000)
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Discovery Completed with Errors')
                            ->body($body)
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                })
                ->visible(fn () => $this->record->driver === 'pace'),

            Actions\Action::make('regenerate_webhook_token')
                ->label('Regenerate Webhook Token')
                ->icon('heroicon-o-key')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Regenerate Webhook Token')
                ->modalDescription('This will invalidate the current webhook URL and generate a new one. Any external systems using the old URL will stop working. Continue?')
                ->action(function () {
                    $this->record->generateWebhookToken();

                    Notification::make()
                        ->title('Webhook Token Regenerated')
                        ->body('A new webhook token has been generated. Update any external systems with the new URL.')
                        ->success()
                        ->duration(10000)
                        ->send();
                })
                ->visible(fn () => $this->record->isPushMode()),

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
        // $hidden strips auth_credentials from toArray(), so read directly from the model
        $encrypted = $this->record->getAttributes()['auth_credentials'] ?? null;

        if (!empty($encrypted)) {
            try {
                $data['credentials'] = json_decode(Crypt::decryptString($encrypted), true);
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
