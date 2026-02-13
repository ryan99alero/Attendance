<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE_DEVICE_OFFLINE = 'device_offline';

    public const TYPE_DEVICE_ONLINE = 'device_online';

    public const TYPE_API_ERROR = 'api_error';

    public const TYPE_JOB_FAILED = 'job_failed';

    public const TYPE_LOGIN_FAILED = 'login_failed';

    public const TYPE_SYNC_ERROR = 'sync_error';

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public array $context = [],
        public ?string $actionUrl = null,
        public ?string $actionText = null
    ) {
        // Set the queue for alert notifications
        $this->onQueue('alerts');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->getSubject())
            ->greeting($this->title)
            ->line($this->message);

        // Add context details
        if (! empty($this->context)) {
            $mail->line('**Details:**');
            foreach ($this->context as $key => $value) {
                $label = ucwords(str_replace('_', ' ', $key));
                $mail->line("- {$label}: {$value}");
            }
        }

        // Add action button if provided
        if ($this->actionUrl && $this->actionText) {
            $mail->action($this->actionText, $this->actionUrl);
        }

        // Add timestamp
        $mail->line('---');
        $mail->line('Time: '.now()->format('Y-m-d H:i:s T'));

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }

    /**
     * Get the email subject based on alert type.
     */
    protected function getSubject(): string
    {
        $prefix = match ($this->type) {
            self::TYPE_DEVICE_OFFLINE => '[ALERT]',
            self::TYPE_DEVICE_ONLINE => '[INFO]',
            self::TYPE_API_ERROR => '[ERROR]',
            self::TYPE_JOB_FAILED => '[FAILED]',
            self::TYPE_LOGIN_FAILED => '[SECURITY]',
            self::TYPE_SYNC_ERROR => '[SYNC ERROR]',
            default => '[SYSTEM]',
        };

        return "{$prefix} {$this->title}";
    }
}
