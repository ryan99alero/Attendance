<?php

namespace App\Mail\Templates;

use App\Contracts\EmailTemplateDefinition;
use App\Models\Device;

class DeviceOfflineAlert implements EmailTemplateDefinition
{
    public function __construct(
        public Device $device,
        public int $offlineMinutes
    ) {}

    public static function getKey(): string
    {
        return 'device.offline';
    }

    public static function getName(): string
    {
        return 'Device Offline Alert';
    }

    public static function getDescription(): string
    {
        return 'Sent when a time clock device goes offline and stops communicating with the system.';
    }

    public static function getAvailableVariables(): array
    {
        return [
            'devices.display_name' => ['label' => 'Device Display Name', 'example' => 'Main Entrance Clock'],
            'devices.device_name' => ['label' => 'Device Name', 'example' => 'CLOCK-001'],
            'devices.serial_number' => ['label' => 'Serial Number', 'example' => 'SN123456789'],
            'devices.ip_address' => ['label' => 'IP Address', 'example' => '192.168.1.100'],
            'devices.last_seen_at' => ['label' => 'Last Seen', 'example' => 'Feb 23, 2026 at 2:30 PM'],
            'devices.offline_minutes' => ['label' => 'Offline Duration (minutes)', 'example' => '15'],
            'departments.name' => ['label' => 'Department Name', 'example' => 'Warehouse'],
            'url' => ['label' => 'Device Details URL', 'example' => 'https://attend.test/admin/devices/1'],
        ];
    }

    public static function getDefaultSubject(): string
    {
        return '[ALERT] Time Clock Offline: {{devices.display_name}}';
    }

    public static function getDefaultBody(): string
    {
        return <<<'BODY'
**Alert: Device Offline**

The following time clock has stopped communicating with the system:

**Device Details:**
- **Name:** {{devices.display_name}}
- **Device ID:** {{devices.device_name}}
- **Serial Number:** {{devices.serial_number}}
- **IP Address:** {{devices.ip_address}}
- **Last Seen:** {{devices.last_seen_at}}
- **Offline Duration:** {{devices.offline_minutes}} minutes

{{#departments.name}}
**Department:** {{departments.name}}
{{/departments.name}}

**Possible Causes:**
- Network connectivity issues
- Power outage or device unplugged
- Device hardware failure
- Firewall or network configuration changes

**Recommended Actions:**
1. Check the physical device and power connection
2. Verify network connectivity
3. Check for any network or firewall changes
4. Contact IT support if the issue persists

[View Device Details]({{url}})

---
This is an automated alert from the Time & Attendance System.
BODY;
    }

    public function getContextualUrl(): string
    {
        return route('filament.admin.resources.devices.edit', ['record' => $this->device->id]);
    }

    public static function getSampleData(): array
    {
        return [
            'devices.display_name' => 'Main Entrance Clock',
            'devices.device_name' => 'CLOCK-001',
            'devices.serial_number' => 'SN123456789',
            'devices.ip_address' => '192.168.1.100',
            'devices.last_seen_at' => 'Feb 23, 2026 at 2:30 PM',
            'devices.offline_minutes' => '15',
            'departments.name' => 'Warehouse',
        ];
    }

    public function buildData(): array
    {
        $department = $this->device->department;

        return [
            'devices.display_name' => $this->device->display_name ?: $this->device->device_name ?: 'Unknown Device',
            'devices.device_name' => $this->device->device_name ?? '',
            'devices.serial_number' => $this->device->serial_number ?? '',
            'devices.ip_address' => $this->device->ip_address ?? '',
            'devices.last_seen_at' => $this->device->last_seen_at?->format('M j, Y \a\t g:i A') ?? 'Never',
            'devices.offline_minutes' => (string) $this->offlineMinutes,
            'departments.name' => $department?->name ?? '',
        ];
    }
}
