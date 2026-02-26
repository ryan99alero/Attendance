<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AnnouncementDismissedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Announcement $announcement,
        public Employee $employee
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement_dismissed',
            'announcement_id' => $this->announcement->id,
            'announcement_title' => $this->announcement->title,
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->full_names,
            'dismissed_at' => now()->toIso8601String(),
            'message' => "{$this->employee->full_names} dismissed your announcement \"{$this->announcement->title}\" without acknowledging.",
        ];
    }
}
