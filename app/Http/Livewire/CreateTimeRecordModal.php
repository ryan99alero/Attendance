<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Attendance;
use App\Filament\Pages\AttendanceSummary;
use App\Services\PunchStateService;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class CreateTimeRecordModal extends Component
{
    public ?string $employeeId = null;
    public ?string $date = null;
    public ?string $punchType = null;
    public ?string $punchTime = null;
    public bool $isOpen = false;
    public ?string $punchState = 'unknown'; // ✅ Default to unknown

    protected $listeners = ['open-create-modal' => 'openCreateModal'];

    protected function rules(): array
    {
        return [
            'employeeId' => 'required|exists:employees,id',
            'date' => 'required|date',
            'punchType' => [
                'required',
                Rule::in(['start_time', 'stop_time', 'lunch_start', 'lunch_stop', 'unclassified']),
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
            'punchTime' => 'required|date_format:H:i',
        ];
    }

    public function updatedPunchType(): void
    {
        Log::info("[CreateTimeRecordModal] PunchType changed: {$this->punchType}");

        // Auto-update PunchState based on the selected PunchType
        $this->punchState = PunchStateService::determinePunchState($this->punchType);

        Log::info("[CreateTimeRecordModal] PunchState updated to: {$this->punchState}");
    }

    public function openCreateModal($employeeId, $date, $punchType): void
    {
        Log::info('[CreateTimeRecordModal] Opened', compact('employeeId', 'date', 'punchType'));

        $this->employeeId = $employeeId;
        $this->date = $date;
        $this->punchType = $punchType;
        $this->punchTime = null;
        $this->punchState = 'unknown'; // ✅ Reset punchState on open
        $this->isOpen = true;
    }
    public function closeModal(): void
    {
        $this->reset();
        $this->isOpen = false;
    }

    public function saveTimeRecord(): void
    {
        $validatedData = $this->validate();

        $punchTypeMapping = [
            'start_time' => 1,
            'stop_time' => 2,
            'lunch_start' => 3,
            'lunch_stop' => 4,
            'unclassified' => 5,
        ];

        if (!isset($punchTypeMapping[$validatedData['punchType']])) {
            Log::error("[CreateTimeRecordModal] Invalid Punch Type: " . $validatedData['punchType']);
            return;
        }

        $punchTypeId = $punchTypeMapping[$validatedData['punchType']];

        try {
            Attendance::create([
                'employee_id' => $validatedData['employeeId'],
                'punch_time' => "{$validatedData['date']} {$validatedData['punchTime']}",
                'punch_type_id' => $punchTypeId,
                'punch_state' => $validatedData['punchState'],
            ]);

            Log::info("[CreateTimeRecordModal] Successfully Created Time Record");

        } catch (\Exception $e) {
            Log::error("[CreateTimeRecordModal] Record Creation Failed", ['error' => $e->getMessage()]);
        }

        $this->dispatch('timeRecordCreated')->to(AttendanceSummary::class);
        $this->dispatch('$refresh');
        $this->closeModal();
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application
    {
        return view('livewire.create-time-record-modal');
    }
}
