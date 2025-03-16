<?php

namespace App\Http\Livewire;

use App\Models\Attendance;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class UpdateTimeRecordModal extends Component
{
    public ?string $attendanceId = null;
    public ?string $employeeId = null;
    public ?string $deviceId = null;
    public ?string $date = null;
    public ?string $punchType = null;
    public ?string $punchState = null;
    public ?string $punchTime = null;
    public bool $isOpen = false;

    protected $listeners = ['open-update-modal' => 'openUpdateModal'];

    // Validation rules
    protected array $rules = [
        'employeeId' => 'required|exists:employees,id',
        'deviceId' => 'nullable|exists:devices,id',
        'date' => 'required|date',
        'punchType' => 'required|in:start_time,stop_time,lunch_start,lunch_stop,unclassified',
        'punchState' => 'required|in:start,stop,unknown',
        'punchTime' => 'required|date_format:H:i:s',
    ];

    public function openUpdateModal($attendanceId, $employeeId, $deviceId, $date, $punchType, $punchState, $existingTime): void
    {
        Log::info('UpdateTimeRecordModal Opened', compact('attendanceId', 'employeeId', 'deviceId', 'date', 'punchType', 'punchState', 'existingTime'));

        $this->attendanceId = $attendanceId;
        $this->employeeId = $employeeId;
        $this->deviceId = $deviceId ?: null; // Ensure null if empty
        $this->date = $date;
        $this->punchType = $punchType;
        $this->punchState = $punchState ?: 'unknown'; // Default to 'unknown' if empty
        $this->punchTime = $existingTime ?: '00:00:00'; // Default to a safe value
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->reset(['attendanceId', 'employeeId', 'deviceId', 'date', 'punchType', 'punchState', 'punchTime']);
        $this->isOpen = false;
    }

    public function updateTimeRecord(): void
    {
        $validatedData = $this->validate([
            'employeeId' => 'required|integer',
            'deviceId' => 'nullable|integer',
            'date' => 'required|date',
            'punchType' => 'required|string',
            'punchState' => 'required|string',
            'punchTime' => 'required|date_format:H:i:s',
        ]);

        $punchTypeMapping = [
            'start_time' => 1,
            'stop_time' => 2,
            'lunch_start' => 3,
            'lunch_stop' => 4,
            'unclassified' => 5,
        ];

        if (!isset($punchTypeMapping[$validatedData['punchType']])) {
            Log::error("[UpdateTimeRecordModal] Invalid Punch Type: " . $validatedData['punchType']);
            return;
        }

        $punchTypeId = $punchTypeMapping[$validatedData['punchType']];

        Attendance::where('id', $this->attendanceId)->update([
            'punch_time' => "{$validatedData['date']} {$validatedData['punchTime']}",
            'punch_type_id' => $punchTypeId,
            'updated_at' => now(),
        ]);

        Log::info("[UpdateTimeRecordModal] Updated Record ID: {$this->attendanceId}");

        // Dispatch event to refresh data and close modal
        $this->dispatch('timeRecordUpdated'); // Refresh data
        $this->dispatch('close-update-modal'); // Close modal

        // Explicitly reset modal fields
        $this->reset();

        // Dispatch event to Livewire to close the modal explicitly
        $this->dispatch('closeModal');
    }

    public function render()
    {
        return view('livewire.update-time-record-modal');
    }
}
