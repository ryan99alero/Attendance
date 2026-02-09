<?php

namespace App\Jobs;

use Exception;
use App\Mail\DeviceBackOnlineAlert;
use App\Mail\DeviceOfflineAlert;
use App\Models\CompanySetup;
use App\Models\Device;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckDeviceOfflineStatus implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * Checks all active devices for offline/online status changes and sends alerts.
     */
    public function handle(): void
    {
        $companySetup = CompanySetup::first();

        if (!$companySetup) {
            Log::warning('CheckDeviceOfflineStatus: No company setup found, skipping.');
            return;
        }

        $thresholdMinutes = $companySetup->device_offline_threshold_minutes ?? 5;
        $companyAlertEmail = $companySetup->device_alert_email;

        // Get all active time clock devices
        $devices = Device::where('is_active', true)
            ->where('device_type', 'esp32_timeclock')
            ->with(['department.manager'])
            ->get();

        foreach ($devices as $device) {
            $this->checkDevice($device, $thresholdMinutes, $companyAlertEmail);
        }
    }

    /**
     * Check a single device and send alerts if needed.
     */
    protected function checkDevice(Device $device, int $thresholdMinutes, ?string $companyAlertEmail): void
    {
        $cutoffTime = now()->subMinutes($thresholdMinutes);

        // Device is considered offline if last_seen_at is older than threshold (or never seen)
        $isOffline = !$device->last_seen_at || $device->last_seen_at->lt($cutoffTime);

        if ($isOffline && !$device->offline_alerted_at) {
            // Device just went offline - send alert
            $this->sendOfflineAlert($device, $companyAlertEmail);
        } elseif (!$isOffline && $device->offline_alerted_at) {
            // Device came back online - send recovery alert
            $this->sendBackOnlineAlert($device, $companyAlertEmail);
        }
    }

    /**
     * Send offline alert and update device record.
     */
    protected function sendOfflineAlert(Device $device, ?string $companyAlertEmail): void
    {
        $recipients = $this->collectRecipients($device, $companyAlertEmail);

        if (empty($recipients)) {
            Log::warning("CheckDeviceOfflineStatus: No recipients for offline alert on device {$device->id}");
            return;
        }

        // Calculate how long device has been offline
        $offlineMinutes = $device->last_seen_at
            ? (int) now()->diffInMinutes($device->last_seen_at)
            : 0;

        try {
            Mail::to($recipients)->send(new DeviceOfflineAlert($device, $offlineMinutes));

            // Mark device as alerted
            $device->update(['offline_alerted_at' => now()]);

            Log::info("CheckDeviceOfflineStatus: Sent offline alert for device {$device->id} to " . implode(', ', $recipients));
        } catch (Exception $e) {
            Log::error("CheckDeviceOfflineStatus: Failed to send offline alert for device {$device->id}: " . $e->getMessage());
        }
    }

    /**
     * Send back online alert and clear offline flag.
     */
    protected function sendBackOnlineAlert(Device $device, ?string $companyAlertEmail): void
    {
        $recipients = $this->collectRecipients($device, $companyAlertEmail);

        if (empty($recipients)) {
            // Still clear the flag even if no recipients
            $device->update(['offline_alerted_at' => null]);
            Log::warning("CheckDeviceOfflineStatus: No recipients for back-online alert on device {$device->id}");
            return;
        }

        // Calculate total downtime
        $downMinutes = $device->offline_alerted_at
            ? (int) now()->diffInMinutes($device->offline_alerted_at)
            : 0;

        try {
            Mail::to($recipients)->send(new DeviceBackOnlineAlert($device, $downMinutes));

            // Clear the offline alert flag
            $device->update(['offline_alerted_at' => null]);

            Log::info("CheckDeviceOfflineStatus: Sent back-online alert for device {$device->id} to " . implode(', ', $recipients));
        } catch (Exception $e) {
            Log::error("CheckDeviceOfflineStatus: Failed to send back-online alert for device {$device->id}: " . $e->getMessage());
            // Still clear the flag to prevent repeated attempts
            $device->update(['offline_alerted_at' => null]);
        }
    }

    /**
     * Collect all email recipients for a device alert.
     *
     * @return array<string>
     */
    protected function collectRecipients(Device $device, ?string $companyAlertEmail): array
    {
        $recipients = [];

        // Add company-wide alert email if set
        if ($companyAlertEmail && filter_var($companyAlertEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $companyAlertEmail;
        }

        // Add department manager email if available
        if ($device->department && $device->department->manager) {
            $managerEmail = $device->department->manager->email;
            if ($managerEmail && filter_var($managerEmail, FILTER_VALIDATE_EMAIL)) {
                // Avoid duplicates
                if (!in_array($managerEmail, $recipients)) {
                    $recipients[] = $managerEmail;
                }
            }
        }

        return $recipients;
    }
}
