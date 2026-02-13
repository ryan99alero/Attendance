<?php

namespace App\Services;

use App\Models\CompanySetup;
use App\Models\Device;
use App\Notifications\SystemAlertNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Centralized alert service for critical system notifications.
 *
 * Usage:
 *   app(AlertService::class)->deviceOffline($device);
 *   app(AlertService::class)->apiError('Pace API', 'Connection timeout', ['endpoint' => '/employees']);
 *   app(AlertService::class)->jobFailed('ProcessPayrollExportJob', 'Out of memory');
 *   app(AlertService::class)->loginFailed('admin@example.com', '192.168.1.100');
 */
class AlertService
{
    /**
     * Rate limit key prefix for alerts.
     */
    protected const RATE_LIMIT_PREFIX = 'alert_rate_limit:';

    /**
     * Default rate limit window in minutes.
     */
    protected const RATE_LIMIT_MINUTES = 15;

    /**
     * Send a device offline alert.
     */
    public function deviceOffline(Device $device): void
    {
        $cacheKey = self::RATE_LIMIT_PREFIX."device_offline:{$device->id}";

        // Rate limit: Only send one alert per device per 15 minutes
        if (Cache::has($cacheKey)) {
            return;
        }

        $this->send(
            type: SystemAlertNotification::TYPE_DEVICE_OFFLINE,
            title: "Device Offline: {$device->device_name}",
            message: "Time clock '{$device->device_name}' has not sent a heartbeat and appears to be offline.",
            context: [
                'device_id' => $device->id,
                'device_name' => $device->device_name,
                'location' => $device->location ?? 'Unknown',
                'last_seen' => $device->last_heartbeat?->format('Y-m-d H:i:s') ?? 'Never',
            ],
            actionUrl: route('filament.admin.resources.devices.index'),
            actionText: 'View Devices'
        );

        Cache::put($cacheKey, true, now()->addMinutes(self::RATE_LIMIT_MINUTES));

        Log::channel('daily')->warning('[AlertService] Device offline alert sent', [
            'device_id' => $device->id,
            'device_name' => $device->device_name,
        ]);
    }

    /**
     * Send a device back online alert.
     */
    public function deviceOnline(Device $device): void
    {
        // Clear the rate limit so future offline alerts can be sent
        Cache::forget(self::RATE_LIMIT_PREFIX."device_offline:{$device->id}");

        $this->send(
            type: SystemAlertNotification::TYPE_DEVICE_ONLINE,
            title: "Device Online: {$device->device_name}",
            message: "Time clock '{$device->device_name}' is back online.",
            context: [
                'device_id' => $device->id,
                'device_name' => $device->device_name,
                'location' => $device->location ?? 'Unknown',
            ]
        );

        Log::channel('daily')->info('[AlertService] Device online alert sent', [
            'device_id' => $device->id,
        ]);
    }

    /**
     * Send an API error alert.
     */
    public function apiError(string $apiName, string $errorMessage, array $context = []): void
    {
        $cacheKey = self::RATE_LIMIT_PREFIX.'api_error:'.md5($apiName.$errorMessage);

        // Rate limit: Same error only once per 15 minutes
        if (Cache::has($cacheKey)) {
            return;
        }

        $this->send(
            type: SystemAlertNotification::TYPE_API_ERROR,
            title: "API Error: {$apiName}",
            message: $errorMessage,
            context: array_merge(['api' => $apiName], $context)
        );

        Cache::put($cacheKey, true, now()->addMinutes(self::RATE_LIMIT_MINUTES));

        Log::channel('api')->error('[AlertService] API error alert sent', [
            'api' => $apiName,
            'error' => $errorMessage,
            'context' => $context,
        ]);
    }

    /**
     * Send a job failed alert.
     */
    public function jobFailed(string $jobName, string $errorMessage, array $context = []): void
    {
        $cacheKey = self::RATE_LIMIT_PREFIX.'job_failed:'.md5($jobName.$errorMessage);

        // Rate limit: Same job failure only once per 15 minutes
        if (Cache::has($cacheKey)) {
            return;
        }

        $this->send(
            type: SystemAlertNotification::TYPE_JOB_FAILED,
            title: "Job Failed: {$jobName}",
            message: $errorMessage,
            context: array_merge(['job' => $jobName], $context),
            actionUrl: url('/telescope/jobs'),
            actionText: 'View in Telescope'
        );

        Cache::put($cacheKey, true, now()->addMinutes(self::RATE_LIMIT_MINUTES));

        Log::channel('jobs')->error('[AlertService] Job failed alert sent', [
            'job' => $jobName,
            'error' => $errorMessage,
        ]);
    }

    /**
     * Send a login failure alert.
     */
    public function loginFailed(string $email, string $ipAddress, int $attemptCount = 1): void
    {
        // Only alert after 3+ failed attempts from same IP
        if ($attemptCount < 3) {
            return;
        }

        $cacheKey = self::RATE_LIMIT_PREFIX.'login_failed:'.md5($email.$ipAddress);

        // Rate limit: Same IP/email combo only once per 15 minutes
        if (Cache::has($cacheKey)) {
            return;
        }

        $this->send(
            type: SystemAlertNotification::TYPE_LOGIN_FAILED,
            title: 'Multiple Failed Login Attempts',
            message: "Multiple failed login attempts detected for email '{$email}' from IP {$ipAddress}.",
            context: [
                'email' => $email,
                'ip_address' => $ipAddress,
                'attempt_count' => $attemptCount,
            ]
        );

        Cache::put($cacheKey, true, now()->addMinutes(self::RATE_LIMIT_MINUTES));

        Log::channel('daily')->warning('[AlertService] Login failure alert sent', [
            'email' => $email,
            'ip' => $ipAddress,
            'attempts' => $attemptCount,
        ]);
    }

    /**
     * Send a sync error alert.
     */
    public function syncError(string $integrationName, string $errorMessage, array $context = []): void
    {
        $cacheKey = self::RATE_LIMIT_PREFIX.'sync_error:'.md5($integrationName.$errorMessage);

        // Rate limit: Same sync error only once per 15 minutes
        if (Cache::has($cacheKey)) {
            return;
        }

        $this->send(
            type: SystemAlertNotification::TYPE_SYNC_ERROR,
            title: "Sync Error: {$integrationName}",
            message: $errorMessage,
            context: array_merge(['integration' => $integrationName], $context)
        );

        Cache::put($cacheKey, true, now()->addMinutes(self::RATE_LIMIT_MINUTES));

        Log::channel('api')->error('[AlertService] Sync error alert sent', [
            'integration' => $integrationName,
            'error' => $errorMessage,
        ]);
    }

    /**
     * Send the notification to configured recipients.
     */
    protected function send(
        string $type,
        string $title,
        string $message,
        array $context = [],
        ?string $actionUrl = null,
        ?string $actionText = null
    ): void {
        $recipients = $this->getAlertRecipients();

        if (empty($recipients)) {
            Log::channel('daily')->warning('[AlertService] No alert recipients configured', [
                'type' => $type,
                'title' => $title,
            ]);

            return;
        }

        $notification = new SystemAlertNotification(
            type: $type,
            title: $title,
            message: $message,
            context: $context,
            actionUrl: $actionUrl,
            actionText: $actionText
        );

        Notification::route('mail', $recipients)->notify($notification);
    }

    /**
     * Get the list of email addresses to send alerts to.
     *
     * @return array<string>
     */
    protected function getAlertRecipients(): array
    {
        $recipients = [];

        // Get the device alert email from company setup
        $companySetup = CompanySetup::first();
        if ($companySetup?->device_alert_email) {
            $recipients[] = $companySetup->device_alert_email;
        }

        // Could also add super_admin users here if desired
        // $admins = User::role('super_admin')->pluck('email')->toArray();
        // $recipients = array_merge($recipients, $admins);

        return array_unique($recipients);
    }
}
