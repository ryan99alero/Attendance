<?php

namespace App\Mail\Templates;

use App\Contracts\EmailTemplateDefinition;
use App\Models\Device;

class DeviceBackOnlineAlert implements EmailTemplateDefinition
{
    public function __construct(
        public Device $device,
        public int $downMinutes
    ) {}

    public static function getKey(): string
    {
        return 'device.online';
    }

    public static function getName(): string
    {
        return 'Device Back Online Alert';
    }

    public static function getDescription(): string
    {
        return 'Sent when a previously offline time clock device comes back online.';
    }

    public static function getAvailableVariables(): array
    {
        return [
            'devices.display_name' => ['label' => 'Device Display Name', 'example' => 'Main Entrance Clock'],
            'devices.device_name' => ['label' => 'Device Name', 'example' => 'CLOCK-001'],
            'devices.serial_number' => ['label' => 'Serial Number', 'example' => 'SN123456789'],
            'devices.ip_address' => ['label' => 'IP Address', 'example' => '192.168.1.100'],
            'devices.back_online_at' => ['label' => 'Back Online Time', 'example' => 'Feb 23, 2026 at 3:00 PM'],
            'devices.down_minutes' => ['label' => 'Total Downtime (minutes)', 'example' => '30'],
            'departments.name' => ['label' => 'Department Name', 'example' => 'Warehouse'],
            'url' => ['label' => 'Device Details URL', 'example' => 'https://attend.test/admin/devices/1'],
        ];
    }

    public static function getDefaultSubject(): string
    {
        return '[RESOLVED] Time Clock Back Online: {{devices.display_name}}';
    }

    public static function getDefaultBody(): string
    {
        return <<<'BODY'
**Resolved: Device Back Online**

The following time clock is now back online and communicating normally:

**Device Details:**
- **Name:** {{devices.display_name}}
- **Device ID:** {{devices.device_name}}
- **Serial Number:** {{devices.serial_number}}
- **IP Address:** {{devices.ip_address}}
- **Back Online:** {{devices.back_online_at}}
- **Total Downtime:** {{devices.down_minutes}} minutes

{{#departments.name}}
**Department:** {{departments.name}}
{{/departments.name}}

The device is now functioning normally. No further action is required.

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
            'devices.back_online_at' => 'Feb 23, 2026 at 3:00 PM',
            'devices.down_minutes' => '30',
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
            'devices.back_online_at' => now()->format('M j, Y \a\t g:i A'),
            'devices.down_minutes' => (string) $this->downMinutes,
            'departments.name' => $department?->name ?? '',
        ];
    }
}
