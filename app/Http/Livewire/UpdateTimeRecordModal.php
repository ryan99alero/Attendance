<?php

namespace App\Http\Livewire;

use Exception;
use App\Models\Attendance;
use App\Models\PunchType;
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
    public ?string $status = null;

    protected $listeners = [
        'open-update-modal' => 'openUpdateModal',
        'deleteTimeRecord' => 'deleteTimeRecord',
    ];

    public function getPunchTypes()
    {
        return PunchType::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(function ($punchType) {
                $key = strtolower(str_replace(' ', '_', $punchType->name));
                return [$key => $punchType->name];
            })
            ->toArray();
    }

    public function getPunchTypeMapping()
    {
        return PunchType::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(function ($punchType) {
                $key = strtolower(str_replace(' ', '_', $punchType->name));
                return [$key => $punchType->id];
            })
            ->toArray();
    }

    protected function rules(): array
    {
        $validPunchTypes = array_keys($this->getPunchTypes());
        $validPunchTypes[] = 'unclassified'; // Add unclassified as valid option

        return [
            'employeeId' => 'required|exists:employees,id',
            'deviceId' => 'nullable|exists:devices,id',
            'date' => 'required|date',
            'punchType' => [
                'required',
                Rule::in($validPunchTypes),
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

    public function openUpdateModal($attendanceId, $employeeId, $deviceId, $date, $punchType, $punchState, $existingTime, $status = null): void
    {
        Log::info('[UpdateTimeRecordModal] Opened', compact('attendanceId', 'employeeId', 'deviceId', 'date', 'punchType', 'punchState', 'existingTime', 'status'));

        $this->attendanceId = $attendanceId;
        $this->employeeId = $employeeId;
        $this->deviceId = $deviceId ?: null;
        $this->date = $date;
        $this->punchType = $punchType;
        $this->punchState = $punchState ?: 'unknown';
        $this->punchTime = $existingTime ?: '00:00:00';
        $this->status = $status;
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

        // Get dynamic punch type mapping
        $punchTypeMapping = $this->getPunchTypeMapping();

        // Handle unclassified as a special case - map to the lowest ID available punch type or 5
        if ($validatedData['punchType'] === 'unclassified') {
            $punchTypeId = 5; // Default unclassified ID
        } else {
            if (!isset($punchTypeMapping[$validatedData['punchType']])) {
                Log::error("[UpdateTimeRecordModal] Invalid Punch Type: " . $validatedData['punchType']);
                return;
            }
            $punchTypeId = $punchTypeMapping[$validatedData['punchType']];
        }

        // Determine new status - if current status is Discrepancy and punch type changed, set to Complete
        $newStatus = $this->status;
        if ($this->status === 'Discrepancy') {
            $newStatus = 'Complete';
            Log::info("[UpdateTimeRecordModal] Status changed from Discrepancy to Complete for Record ID: {$this->attendanceId}");
        }

        try {
            $updateData = [
                'punch_time' => "{$validatedData['date']} {$validatedData['punchTime']}",
                'punch_type_id' => $punchTypeId,
                'punch_state' => $validatedData['punchState'],
                'device_id' => $validatedData['deviceId'],
                'updated_at' => now(),
            ];

            // Add status update if it changed
            if ($newStatus !== $this->status) {
                $updateData['status'] = $newStatus;
            }

            Attendance::where('id', $this->attendanceId)->update($updateData);

            Log::info("[UpdateTimeRecordModal] Successfully Updated Record ID: {$this->attendanceId}");

        } catch (Exception $e) {
            Log::error("[UpdateTimeRecordModal] Update Failed", ['error' => $e->getMessage()]);
        }

        $this->dispatch('timeRecordUpdated');
        $this->dispatch('close-update-modal');

        $this->reset();
    }

    public function acceptPunchType(): void
    {
        // Only available for Discrepancy status
        if ($this->status !== 'Discrepancy') {
            Log::warning("[UpdateTimeRecordModal] Accept Punch Type called for non-Discrepancy record: {$this->attendanceId}");
            return;
        }

        try {
            // Change status from Discrepancy to Complete without changing punch type
            Attendance::where('id', $this->attendanceId)->update([
                'status' => 'Complete',
                'updated_at' => now(),
            ]);

            Log::info("[UpdateTimeRecordModal] Accepted punch type for Record ID: {$this->attendanceId} - Status changed to Complete");

        } catch (Exception $e) {
            Log::error("[UpdateTimeRecordModal] Accept Punch Type Failed", ['error' => $e->getMessage()]);
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
