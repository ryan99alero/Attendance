<x-mail::message>
# Time Clock Back Online

Good news! The following time clock is now back online:

**Device:** {{ $device->display_name ?: $device->device_name ?: 'Unknown' }}

**Back Online At:** {{ now()->format('M j, Y g:i A') }}

**Total Downtime:** {{ $downMinutes }} minutes

@if($device->department)
**Department:** {{ $device->department->name }}
@endif

@if($device->last_ip)
**Current IP Address:** {{ $device->last_ip }}
@endif

---

The device is now responding normally and employees can resume using it for time tracking.

<x-mail::button :url="config('app.url') . '/admin/devices/' . $device->id">
View Device Details
</x-mail::button>

This is an automated alert from the Attend Time Clock System.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
