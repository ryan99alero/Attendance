<?php

namespace App\Notifications;

use App\Models\Announcement;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Announcement $announcement
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification for Filament.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $creatorName = $this->announcement->creator?->name ?? 'System';
        $bodyWithSender = "**From:** {$creatorName}\n\n{$this->announcement->body}";
        $body = Str::markdown($bodyWithSender);

        $notification = FilamentNotification::make()
            ->title($this->announcement->title)
            ->body($body)
            ->icon($this->getIconForPriority())
            ->iconColor($this->getColorForPriority())
            ->persistent();

        // Add action buttons for announcements requiring acknowledgment
        if ($this->announcement->require_acknowledgment) {
            $notification->actions([
                Action::make('acknowledge')
                    ->label('Acknowledge')
                    ->color('success')
                    ->button()
                    ->dispatch('acknowledgeAnnouncement', [$this->announcement->id])
                    ->close(),
                Action::make('dismiss')
                    ->label('Dismiss Without Acknowledging')
                    ->color('gray')
                    ->dispatch('dismissAnnouncement', [$this->announcement->id])
                    ->close(),
            ]);
        } else {
            $notification->actions([
                Action::make('markRead')
                    ->label('Mark as Read')
                    ->color('gray')
                    ->dispatch('markAnnouncementRead', [$this->announcement->id])
                    ->markAsRead()
                    ->close(),
            ]);
        }

        return $notification->getDatabaseMessage();
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
            'priority' => $this->announcement->priority,
            'audio_type' => $this->announcement->audio_type,
            'require_acknowledgment' => $this->announcement->require_acknowledgment,
            'created_by' => $this->announcement->creator ? [
                'id' => $this->announcement->creator->id,
                'name' => $this->announcement->creator->name,
            ] : null,
        ];
    }

    protected function getIconForPriority(): string
    {
        return match ($this->announcement->priority) {
            Announcement::PRIORITY_URGENT => 'heroicon-o-exclamation-triangle',
            Announcement::PRIORITY_HIGH => 'heroicon-o-exclamation-circle',
            Announcement::PRIORITY_LOW => 'heroicon-o-information-circle',
            default => 'heroicon-o-megaphone',
        };
    }

    protected function getColorForPriority(): string
    {
        return match ($this->announcement->priority) {
            Announcement::PRIORITY_URGENT => 'danger',
            Announcement::PRIORITY_HIGH => 'warning',
            Announcement::PRIORITY_LOW => 'gray',
            default => 'info',
        };
    }
}
