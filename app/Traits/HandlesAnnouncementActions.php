<?php

namespace App\Traits;

use App\Models\Announcement;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

trait HandlesAnnouncementActions
{
    #[On('acknowledgeAnnouncement')]
    public function acknowledgeAnnouncement(mixed $announcementId = null): void
    {
        // Handle both array and direct value formats
        if (is_array($announcementId)) {
            $announcementId = $announcementId[0] ?? $announcementId['announcementId'] ?? null;
        }

        $announcement = Announcement::find($announcementId);
        $employee = Auth::user()?->employee;

        if (! $announcement || ! $employee) {
            Notification::make()
                ->title('Error')
                ->body('Unable to acknowledge announcement.')
                ->danger()
                ->send();

            return;
        }

        $announcement->acknowledgeBy($employee, 'portal');

        Notification::make()
            ->title('Acknowledged')
            ->body("You've acknowledged \"{$announcement->title}\".")
            ->success()
            ->send();
    }

    #[On('dismissAnnouncement')]
    public function dismissAnnouncement(mixed $announcementId = null): void
    {
        // Handle both array and direct value formats
        if (is_array($announcementId)) {
            $announcementId = $announcementId[0] ?? $announcementId['announcementId'] ?? null;
        }

        $announcement = Announcement::find($announcementId);
        $employee = Auth::user()?->employee;

        if (! $announcement || ! $employee) {
            Notification::make()
                ->title('Error')
                ->body('Unable to dismiss announcement.')
                ->danger()
                ->send();

            return;
        }

        $announcement->dismissBy($employee, 'portal');

        Notification::make()
            ->title('Dismissed')
            ->body("You've dismissed \"{$announcement->title}\" without acknowledging.")
            ->warning()
            ->send();
    }

    #[On('markAnnouncementRead')]
    public function markAnnouncementRead(mixed $announcementId = null): void
    {
        // Handle both array and direct value formats
        if (is_array($announcementId)) {
            $announcementId = $announcementId[0] ?? $announcementId['announcementId'] ?? null;
        }

        $announcement = Announcement::find($announcementId);
        $employee = Auth::user()?->employee;

        if (! $announcement || ! $employee) {
            return;
        }

        $announcement->markAsReadBy($employee, 'portal');
    }
}
