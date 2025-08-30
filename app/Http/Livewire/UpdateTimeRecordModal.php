<?php

namespace App\Http\Livewire;

use Exception;
use App\Models\Attendance;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use App\Services\PunchStateService;
use Illuminate\Validation\Rule;

class UpdateTimeRecordModal extends Component
{
    public ?string $attendanceId = null;
    public ?string $employeeId = null;
    public ?string $deviceId = null;
    public ?string $date = null;
    public ?string $punchType = null;
    public ?string $punchTime = null;
    public bool $isOpen = false;
    public ?string $punchState = 'unknown';

    protected $listeners = [
        'open-update-modal' => 'openUpdateModal',
        'deleteTimeRecord' => 'deleteTimeRecord',
    ];

    protected function rules(): array
    {
        return [
            'employeeId' => 'required|exists:employees,id',
            'deviceId' => 'nullable|exists:devices,id',
            'date' => 'required|date',
            'punchType' => [
                'required',
                Rule::in(['start_time', 'stop_time', 'lunch_start', 'lunch_stop']),
            ],
            'punchState' => [
                'required',
                Rule::in(['start', 'stop', 'unknown']),
                function ($attribute, $value, $fail) {
                    if ($value === 'unknown') {
                        $fail('You must select Start or Stop before saving.');
                    }
                },
            ],
            'punchTime' => 'required|date_format:H:i:s',
        ];
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'punchType') {
            Log::info("[UpdateTimeRecordModal] PunchType changed: {$this->punchType}");

            // Force updating the PunchState
            $newPunchState = PunchStateService::determinePunchState($this->punchType, 'unknown');

            Log::info("[UpdateTimeRecordModal] Old PunchState: {$this->punchState} | New PunchState: {$newPunchState}");

            $this->punchState = $newPunchState;
        }
    }

    public function openUpdateModal(...$params): void
    {
        // When Alpine dispatches an object, Livewire passes the values as arguments
        // Expected order: attendanceId, employeeId, deviceId, date, punchType, existingTime, punchState
        $attendanceId = $params[0] ?? null;
        $employeeId = $params[1] ?? null;
        $deviceId = $params[2] ?? null;
        $date = $params[3] ?? null;
        $punchType = $params[4] ?? null;
        $existingTime = $params[5] ?? null;
        $punchState = $params[6] ?? null;

        Log::info('[UpdateTimeRecordModal] Opened', compact('attendanceId', 'employeeId', 'deviceId', 'date', 'punchType', 'punchState', 'existingTime'));
        Log::info('[UpdateTimeRecordModal] Raw params', $params);

        $this->attendanceId = $attendanceId;
        $this->employeeId = $employeeId;
        $this->deviceId = $deviceId ?: null;
        $this->date = $date;
        $this->punchType = $punchType;
        $this->punchState = $punchState ?: 'unknown';
        $this->punchTime = $existingTime ?: '00:00:00';
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->reset();
        $this->isOpen = false;
    }

    public function updateTimeRecord(): void
    {
        $validatedData = $this->validate();

        $punchTypeMapping = [
            'start_time' => 1,
            'stop_time' => 2,
            'lunch_start' => 3,
            'lunch_stop' => 4,
        ];

        if (!isset($punchTypeMapping[$validatedData['punchType']])) {
            Log::error("[UpdateTimeRecordModal] Invalid Punch Type: " . $validatedData['punchType']);
            return;
        }

        $punchTypeId = $punchTypeMapping[$validatedData['punchType']];

        try {
            Attendance::where('id', $this->attendanceId)->update([
                'punch_time' => "{$validatedData['date']} {$validatedData['punchTime']}",
                'punch_type_id' => $punchTypeId,
                'punch_state' => $validatedData['punchState'],
                'device_id' => $validatedData['deviceId'],
                'updated_at' => now(),
            ]);

            Log::info("[UpdateTimeRecordModal] Successfully Updated Record ID: {$this->attendanceId}");

        } catch (Exception $e) {
            Log::error("[UpdateTimeRecordModal] Update Failed", ['error' => $e->getMessage()]);
        }

        $this->dispatch('timeRecordUpdated');
        $this->dispatch('close-update-modal');

        $this->reset();
    }

    public function deleteTimeRecord(): void
    {
        try {
            Attendance::findOrFail($this->attendanceId)->delete();
            Log::info("[UpdateTimeRecordModal] Deleted attendance record: {$this->attendanceId}");

            $this->dispatch('timeRecordUpdated');
            $this->dispatch('$refresh');
            $this->dispatch('close-update-modal');
            $this->reset();
        } catch (Exception $e) {
            Log::error("[UpdateTimeRecordModal] Failed to delete record", ['error' => $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.update-time-record-modal');
    }
}
