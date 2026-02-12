<?php

namespace App\Filament\Resources\SystemLogResource\Pages;

use App\Filament\Resources\SystemLogResource;
use App\Models\CompanySetup;
use App\Models\SystemLog;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSystemLogs extends ListRecords
{
    protected static string $resource = SystemLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('purge_old_logs')
                ->label('Purge Old Logs')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Purge Old Logs')
                ->modalDescription(function () {
                    $retentionDays = CompanySetup::first()?->log_retention_days ?? 30;
                    $count = SystemLog::where('created_at', '<', Carbon::now()->subDays($retentionDays))->count();

                    return "This will permanently delete {$count} log entries older than {$retentionDays} days. This action cannot be undone.";
                })
                ->action(function () {
                    $retentionDays = CompanySetup::first()?->log_retention_days ?? 30;
                    $deleted = SystemLog::where('created_at', '<', Carbon::now()->subDays($retentionDays))->delete();

                    Notification::make()
                        ->success()
                        ->title('Logs Purged')
                        ->body("Deleted {$deleted} old log entries.")
                        ->send();
                }),

            Action::make('clear_all_logs')
                ->label('Clear All')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clear All Logs')
                ->modalDescription('This will permanently delete ALL system logs. This action cannot be undone.')
                ->action(function () {
                    SystemLog::truncate();

                    Notification::make()
                        ->success()
                        ->title('All Logs Cleared')
                        ->body('All system logs have been deleted.')
                        ->send();
                }),
        ];
    }
}
