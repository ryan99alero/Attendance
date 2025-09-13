<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Attendance;
use App\Models\PunchType;
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

        // Determine and set punch state
        $determinedPunchState = PunchStateService::determinePunchState($punchType);
        $this->punchState = $determinedPunchState;

        Log::info('[CreateTimeRecordModal] PunchState set to: ' . $this->punchState);

        $this->isOpen = true;
    }
    public function closeModal(): void
    {
        $this->reset();
        $this->isOpen = false;
    }

    public function saveTimeRecord(): void
    {
        Log::info('[CreateTimeRecordModal] Save attempt with punchState: ' . $this->punchState);

        $validatedData = $this->validate();

        // Get dynamic punch type mapping
        $punchTypeMapping = $this->getPunchTypeMapping();

        // Handle unclassified as a special case - map to the lowest ID available punch type or 5
        if ($validatedData['punchType'] === 'unclassified') {
            $punchTypeId = 5; // Default unclassified ID
        } else {
            if (!isset($punchTypeMapping[$validatedData['punchType']])) {
                Log::error("[CreateTimeRecordModal] Invalid Punch Type: " . $validatedData['punchType']);
                return;
            }
            $punchTypeId = $punchTypeMapping[$validatedData['punchType']];
        }

        try {
            $record = Attendance::create([
                'employee_id' => $validatedData['employeeId'],
                'punch_time' => "{$validatedData['date']} {$validatedData['punchTime']}",
                'punch_type_id' => $punchTypeId,
                'punch_state' => $validatedData['punchState'],
                'shift_date' => $validatedData['date'],
            ]);

            Log::info('[CreateTimeRecordModal] Created Record', [
                'id' => $record->id,
                'employee_id' => $record->employee_id,
                'punch_time' => $record->punch_time,
                'punch_type_id' => $record->punch_type_id,
                'punch_state' => $record->punch_state,
                'shift_date' => $record->shift_date,
                'created_by' => $record->created_by,
                'timestamp' => now()->toDateTimeString(),
                'exists' => $record->exists,
                'wasRecentlyCreated' => $record->wasRecentlyCreated,
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
