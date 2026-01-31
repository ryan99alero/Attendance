<x-mail::message>
# Time Clock Offline Alert

The following time clock has stopped responding and may be offline:

**Device:** {{ $device->display_name ?: $device->device_name ?: 'Unknown' }}

**Last Seen:** {{ $device->last_seen_at?->format('M j, Y g:i A') ?? 'Never' }}

**Offline Duration:** {{ $offlineMinutes }} minutes

@if($device->department)
**Department:** {{ $device->department->name }}
@endif

@if($device->last_ip)
**Last IP Address:** {{ $device->last_ip }}
@endif

---

**Possible Causes:**
- Power outage or device unplugged
- Network connectivity issues
- Device hardware failure
- WiFi connection lost

**Recommended Actions:**
1. Check if the device has power
2. Verify network connectivity
3. Check the device display for error messages
4. Try power cycling the device

<x-mail::button :url="config('app.url') . '/admin/devices/' . $device->id">
View Device Details
</x-mail::button>

This is an automated alert from the Attend Time Clock System.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
