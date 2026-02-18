<?php

namespace App\Filament\Resources\IntegrationConnectionResource\Pages;

use App\Filament\Resources\IntegrationConnectionResource;
use App\Models\Classification;
use App\Services\Integrations\IntegrationSyncEngine;
use App\Services\Integrations\PaceApiClient;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class EditIntegrationConnection extends EditRecord
{
    protected static string $resource = IntegrationConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_connection')
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
                            $response = Http::timeout($this->record->timeout_seconds)
                                ->get($this->record->base_url);

                            $this->record->markConnected();
                            $result = [
                                'success' => true,
                                'message' => 'Connection successful (HTTP '.$response->status().')',
                            ];
                        } catch (Exception $e) {
                            $this->record->markError($e->getMessage());
                            $result = [
                                'success' => false,
                                'message' => $e->getMessage(),
                            ];
                        }
                    }

                    if ($result['success']) {
                        $body = $result['message'];
                        if (! empty($result['version'])) {
                            $body .= "\nPace Version: ".$result['version'];
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

            Action::make('discover_objects')
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

            Action::make('regenerate_webhook_token')
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

            Action::make('force_sync')
                ->label('Force Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Force Sync Now')
                ->modalDescription('This will immediately run a full sync for all enabled objects on this connection. Continue?')
                ->action(function () {
                    try {
                        $objects = $this->record->objects()
                            ->where('sync_enabled', true)
                            ->where('sync_direction', '!=', 'push')
                            ->get();

                        if ($objects->isEmpty()) {
                            Notification::make()
                                ->title('No Objects to Sync')
                                ->body('No objects are enabled for pull sync on this connection.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $client = new PaceApiClient($this->record);
                        $engine = new IntegrationSyncEngine($client);

                        $results = [];
                        $hasErrors = false;

                        foreach ($objects as $object) {
                            $result = $engine->sync($object);
                            $results[] = [
                                'name' => $object->display_name ?? $object->object_name,
                                'created' => $result->created,
                                'updated' => $result->updated,
                                'skipped' => $result->skipped,
                                'errors' => $result->errors,
                            ];
                            if (! empty($result->errors)) {
                                $hasErrors = true;
                            }
                        }

                        $this->record->markSynced();

                        // Build summary
                        $lines = [];
                        foreach ($results as $r) {
                            $line = "{$r['name']}: {$r['created']} created, {$r['updated']} updated, {$r['skipped']} skipped";
                            if (! empty($r['errors'])) {
                                $errorCount = is_array($r['errors']) ? count($r['errors']) : $r['errors'];
                                $line .= " ({$errorCount} errors)";
                            }
                            $lines[] = $line;
                        }
                        $body = implode("\n", $lines);

                        if ($hasErrors) {
                            Notification::make()
                                ->title('Sync Completed with Errors')
                                ->body($body)
                                ->warning()
                                ->persistent()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Sync Completed')
                                ->body($body)
                                ->success()
                                ->duration(15000)
                                ->send();
                        }
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                })
                ->visible(fn () => $this->record->driver === 'pace'),

            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // $hidden strips auth_credentials from toArray(), so read directly from the model
        $encrypted = $this->record->getAttributes()['auth_credentials'] ?? null;

        if (! empty($encrypted)) {
            try {
                $data['credentials'] = json_decode(Crypt::decryptString($encrypted), true);
            } catch (Exception $e) {
                $data['credentials'] = [];
            }
        }

        // Load ADP code mappings from classifications
        $classifications = Classification::where('is_regular', false)
            ->where('is_overtime', false)
            ->get();

        $data['adp_codes'] = [];
        foreach ($classifications as $classification) {
            $data['adp_codes'][$classification->id] = $classification->adp_code;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Encrypt credentials before saving
        if (isset($data['credentials']) && is_array($data['credentials'])) {
            // Filter out empty values but keep the structure
            $credentials = array_filter($data['credentials'], fn ($value) => $value !== null && $value !== '');
            $data['auth_credentials'] = Crypt::encryptString(json_encode($credentials));
            unset($data['credentials']);
        }

        // Save ADP code mappings to classifications
        if (isset($data['adp_codes']) && is_array($data['adp_codes'])) {
            foreach ($data['adp_codes'] as $classificationId => $adpCode) {
                Classification::where('id', $classificationId)
                    ->update(['adp_code' => $adpCode ?: null]);
            }
            unset($data['adp_codes']);
        }

        $data['updated_by'] = auth()->id();

        return $data;
    }
}
