<?php

namespace App\Http\Livewire;

use App\Filament\Pages\AttendanceSummary;
use App\Models\Attendance;
use Livewire\Component;

class UpdateTimeRecordModal extends Component
{
    public ?string $employeeId = null;
    public ?string $date = null;
    public ?string $punchType = null;
    public ?string $punchTime = null;
    public bool $isOpen = false;

    protected $listeners = ['open-modal' => 'openModal'];

    // Validation rules
    protected array $rules = [
        'employeeId' => 'required|exists:employees,id',
        'date' => 'required|date',
        'punchType' => 'required|in:1,2,3,4',
        'punchTime' => 'required|date_format:H:i', // Validate the time format
    ];

    public function openModal($employeeId, $date, $punchType): void
    {
        $this->employeeId = $employeeId;
        $this->date = $date;
        $this->punchType = $punchType;
        $this->punchTime = null; // Reset punch time
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->reset(['employeeId', 'date', 'punchType', 'punchTime']);
        $this->isOpen = false;
    }

    public function saveTimeRecord(): void
    {
        $this->validate();

        Attendance::create([
            'employee_id' => $this->employeeId,
            'punch_time' => $this->date . ' ' . $this->punchTime,
            'punch_type_id' => $this->punchType,
        ]);

        // Emit event to refresh the AttendanceSummary page
        $this->dispatch('timeRecordCreated')->to(AttendanceSummary::class);

        // Force Livewire to refresh all components
        $this->dispatch('$refresh');

        // Close the modal
        $this->closeModal();
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application
    {
        return view('livewire.create-time-record-modal');
    }
}
