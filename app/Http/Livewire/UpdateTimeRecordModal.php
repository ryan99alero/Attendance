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

    protected $listeners = ['open-update-modal' => 'openModal'];

    // Validation rules
    protected array $rules = [
        'employeeId' => 'required|exists:employees,id',
        'date' => 'required|date',
        'punchType' => 'required|in:1,2,3,4',
        'punchTime' => 'required|date_format:H:i', // Validate the time format
    ];

    public function openModal($attendanceId, $employeeId, $date, $punchType, $existingTime): void
    {
        \Log::info('UpdateTimeRecordModal Opened', compact('attendanceId', 'employeeId', 'date', 'punchType', 'existingTime'));

        $this->attendanceId = $attendanceId;
        $this->employeeId = $employeeId;
        $this->date = $date;
        $this->punchType = $punchType;
        $this->punchTime = $existingTime;
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

        if (!$this->attendanceId) {
            // If there's no attendanceId, show an error message and exit
            session()->flash('error', 'No attendance record selected for update.');
            return;
        }

        $attendance = Attendance::find($this->attendanceId);

        if (!$attendance) {
            // If no matching record found, show an error message and exit
            session()->flash('error', 'Attendance record not found.');
            return;
        }

        // Update the existing record
        $attendance->update([
            'punch_time' => $this->date . ' ' . $this->punchTime,
            'punch_type_id' => $this->punchType,
        ]);
        \Log::info('[UpdateTimeRecordModal] Save button clicked', [
            'attendanceId' => $this->attendanceId,
            'employeeId' => $this->employeeId,
            'date' => $this->date,
            'punchType' => $this->punchType,
            'punchTime' => $this->punchTime,
        ]);
        // Emit event to refresh the AttendanceSummary page
        $this->dispatch('timeRecordUpdated')->to(AttendanceSummary::class);

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
