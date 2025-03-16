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
        try {
            $validatedData = $this->validate();
            Log::info('Validation Passed', ['validatedData' => $validatedData]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', 'Validation failed. Please check your inputs.');
            return;
        }

        if (!$this->attendanceId) {
            session()->flash('error', 'No attendance record selected for update.');
            return;
        }

        $attendance = Attendance::find($this->attendanceId);
        if (!$attendance) {
            session()->flash('error', 'Attendance record not found.');
            return;
        }

        $formattedPunchTime = "{$this->date} {$this->punchTime}";

        $attendance->update([
            'punch_time' => $formattedPunchTime,
            'punch_type_id' => $this->punchType,
            'punch_state' => $this->punchState,
        ]);

        Log::info('Update Successful', [
            'attendanceId' => $this->attendanceId,
            'employeeId' => $this->employeeId,
            'deviceId' => $this->deviceId,
            'date' => $this->date,
            'punchType' => $this->punchType,
            'punchState' => $this->punchState,
            'punchTime' => $this->punchTime,
        ]);

        $this->dispatch('timeRecordUpdated');
        $this->dispatch('$refresh');

        $this->closeModal();
    }

    public function render()
    {
        return view('livewire.update-time-record-modal');
    }
}
