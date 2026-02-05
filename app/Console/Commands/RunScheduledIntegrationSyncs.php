<?php

namespace App\Console\Commands;

use App\Models\IntegrationConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunScheduledIntegrationSyncs extends Command
{
    protected $signature = 'integration:run-scheduled-syncs';

    protected $description = 'Check all polling-enabled integration connections and run syncs that are due.';

    public function handle(): int
    {
        $connections = IntegrationConnection::pollingEnabled()->get();

        if ($connections->isEmpty()) {
            $this->info('No polling-enabled connections found.');
            return 0;
        }

        $this->info("Found {$connections->count()} polling-enabled connection(s).");

        $ran = 0;

        foreach ($connections as $connection) {
            if (!$connection->isDueForSync()) {
                $lastSynced = $connection->last_synced_at?->diffForHumans() ?? 'never';
                $this->line("  [{$connection->name}] Not due yet (last synced {$lastSynced}, interval: {$connection->sync_interval_minutes}m).");
                continue;
            }

            $this->info("  [{$connection->name}] Due for sync — dispatching...");

            try {
                $command = match ($connection->driver) {
                    'pace' => 'pace:sync-employees',
                    default => null,
                };

                if ($command === null) {
                    $this->warn("  [{$connection->name}] No sync command registered for driver '{$connection->driver}'. Skipping.");
                    continue;
                }

                $exitCode = Artisan::call($command, [
                    '--connection' => $connection->id,
                ]);

                $connection->markSynced();
                $ran++;

                if ($exitCode === 0) {
                    $this->info("  [{$connection->name}] Sync completed successfully.");
                } else {
                    $this->warn("  [{$connection->name}] Sync completed with errors (exit code {$exitCode}).");
                }
            } catch (\Exception $e) {
                $this->error("  [{$connection->name}] Sync failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Done. Ran {$ran} sync(s).");

        return 0;
    }
}
