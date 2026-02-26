<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Services\AnnouncementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Send notifications to targeted employees
        $service = app(AnnouncementService::class);
        $count = $service->sendAnnouncement($this->record);

        if ($count > 0) {
            Notification::make()
                ->title('Notifications Sent')
                ->body("Announcement sent to {$count} employee(s).")
                ->success()
                ->send();
        }
    }
}
