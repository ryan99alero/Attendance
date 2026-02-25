<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Services\EmailTemplateService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncTemplates')
                ->label('Sync Templates')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    $result = app(EmailTemplateService::class)->syncTemplates();

                    if ($result['created'] > 0) {
                        Notification::make()
                            ->title('Templates synced')
                            ->body("{$result['created']} new template(s) created, {$result['skipped']} existing template(s) unchanged.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('All templates up to date')
                            ->body('No new templates to sync.')
                            ->info()
                            ->send();
                    }
                }),
        ];
    }
}
