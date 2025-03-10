<?php

namespace App\Http\Livewire;

use App\Filament\Pages\AttendanceSummary;
use App\Models\Attendance;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class UpdateTimeRecordModal extends Component
{
    public ?string $attendanceId = null; // ✅ Ensure this is declared
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
        'punchType' => 'required|in:1,2,3,4',
        'punchState' => 'required|in:start,stop,unknown',
        'punchTime' => 'required|date_format:H:i:s', // ✅ Updated to allow H:i:s format
    ];

    public function openUpdateModal($attendanceId, $employeeId, $deviceId, $date, $punchType, $punchState, $existingTime): void
    {
        \Log::info('UpdateTimeRecordModal Opened', compact('attendanceId', 'employeeId', 'date', 'punchType', 'punchState', 'existingTime'));

        $this->attendanceId = $attendanceId;
        $this->employeeId = $employeeId;
        $this->deviceId = $deviceId;
        $this->date = $date;
        $this->punchType = $punchType;
        $this->punchState = $punchState; // ✅ Store punchState in the modal
        $this->punchTime = $existingTime;
        $this->isOpen = true;
        Log::info('[UpdateTimeRecordModal] Exiting function: ' . __FUNCTION__, [
            'finalState' => $this->someVariable ?? 'N/A'
        ]);
    }

    public function closeModal(): void
    {
        Log::info('[UpdateTimeRecordModal] Entering function: ' . __FUNCTION__, [
            'parameters' => func_get_args()
        ]);
        $this->reset(['employeeId', 'deviceId', 'date', 'punchType', 'punchState', 'punchTime']);
        $this->isOpen = false;
        Log::info('[UpdateTimeRecordModal] Exiting function: ' . __FUNCTION__, [
            'finalState' => $this->someVariable ?? 'N/A'
        ]);
    }

    public function updateTimeRecord(): void
    {
        Log::info('[UpdateTimeRecordModal] Entering function: ' . __FUNCTION__, [
            'parameters' => func_get_args()
        ]);

        try {
            $validatedData = $this->validate();
            Log::info('[UpdateTimeRecordModal] Validation Passed', ['validatedData' => $validatedData]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('[UpdateTimeRecordModal] Validation Failed', ['errors' => $e->errors()]);
            session()->flash('error', 'Validation failed. Please check your inputs.');
            return;
        }

        if (!$this->attendanceId) {
            session()->flash('error', 'No attendance record selected for update.');
            return;
        }

        // ✅ Ensure the attendance record is fetched properly
        $attendance = Attendance::where('id', $this->attendanceId)->first();

        if (!$attendance) {
            session()->flash('error', 'Attendance record not found.');
            return;
        }

        // ✅ Ensure punch time is in the correct format
        $formattedPunchTime = "{$this->date} {$this->punchTime}";

        // ✅ Update the existing record
        $attendance->update([
            'punch_time' => $formattedPunchTime,
            'punch_type_id' => $this->punchType,
        ]);

        Log::info('[UpdateTimeRecordModal] Update Successful', [
            'attendanceId' => $this->attendanceId,
            'employeeId' => $this->employeeId,
            'deviceId' => $this->deviceId,
            'date' => $this->date,
            'punchType' => $this->punchType,
            'punchState' => $this->punchState,
            'punchTime' => $this->punchTime,
        ]);

        // ✅ Emit event to refresh the AttendanceSummary page
        $this->dispatch('timeRecordUpdated');


        // ✅ Force Livewire to refresh all components
        $this->dispatch('$refresh');

        // ✅ Close the modal
        $this->closeModal();
        Log::info('[UpdateTimeRecordModal] Exiting function: ' . __FUNCTION__);
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application
    {
        Log::info('[UpdateTimeRecordModal] Entering function: ' . __FUNCTION__, [
            'parameters' => func_get_args()
        ]);
        return view('livewire.update-time-record-modal');

    }
}
