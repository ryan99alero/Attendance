<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncLog;
use App\Services\Integrations\IntegrationSyncEngine;
use App\Services\Integrations\PaceApiClient;
use Illuminate\Http\JsonResponse;

class WebhookSyncController extends Controller
{
    public function trigger(string $token, ?string $object = null): JsonResponse
    {
        $connection = IntegrationConnection::where('webhook_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            return response()->json(['error' => 'Invalid or inactive webhook token.'], 404);
        }

        $syncLog = IntegrationSyncLog::start(
            connectionId: $connection->id,
            operation: 'webhook_sync',
        );

        try {
            // Build driver-specific client
            if ($connection->driver === 'pace') {
                $client = new PaceApiClient($connection);
            } else {
                $syncLog->markFailed('Unsupported driver for webhook sync: ' . $connection->driver);
                return response()->json(['error' => 'Unsupported driver.'], 422);
            }

            $engine = new IntegrationSyncEngine($client);

            // Query sync-enabled objects, optionally filtered by name
            $objectsQuery = $connection->objects()->syncEnabled();
            if ($object) {
                $objectsQuery->where('object_name', $object);
            }
            $objects = $objectsQuery->get();

            if ($objects->isEmpty()) {
                $msg = $object
                    ? "No sync-enabled object named '{$object}' found."
                    : 'No sync-enabled objects found.';

                $syncLog->markFailed($msg);
                return response()->json(['error' => $msg], 404);
            }

            $aggregated = [
                'fetched' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
            $errors = [];

            foreach ($objects as $obj) {
                $result = $engine->sync($obj);

                $stats = $result->toArray();
                $aggregated['fetched'] += $stats['fetched'];
                $aggregated['created'] += $stats['created'];
                $aggregated['updated'] += $stats['updated'];
                $aggregated['skipped'] += $stats['skipped'];
                $aggregated['failed'] += $stats['failed'];

                if ($result->hasErrors()) {
                    $errors = array_merge($errors, $result->errorMessages);
                }
            }

            $connection->markSynced();

            if (!empty($errors)) {
                $syncLog->markPartial($aggregated, implode('; ', $errors));
            } else {
                $syncLog->markSuccess($aggregated);
            }

            return response()->json([
                'success' => true,
                'objects_synced' => $objects->count(),
                'stats' => $aggregated,
                'errors' => $errors,
            ]);
        } catch (Throwable $e) {
            $syncLog->markFailed($e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
