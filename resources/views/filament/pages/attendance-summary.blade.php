<x-filament::page>
    <style>
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }
        .attendance-table th,
        .attendance-table td {
            border: 1px solid #d1d5db;
            padding: 0.5rem 1rem;
        }
        .attendance-table thead tr {
            background-color: #f3f4f6;
        }
        .dark .attendance-table th,
        .dark .attendance-table td {
            border-color: #374151;
        }
        .dark .attendance-table thead tr {
            background-color: #1f2937;
        }
    </style>
    <div>
        <!-- Filter Form -->
        <form wire:submit.prevent="updateAttendances">
            <div class="space-y-6">
                {{ $this->form }}
            </div>
        </form>

        <!-- Attendance Table -->
        <div class="mt-6">
            <div class="overflow-x-auto">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" wire:model="selectAll">
                            </th>
                            <th>Employee</th>
                            <th>Shift Date</th>
                            @foreach ($this->getVisibleColumns() as $key => $label)
                                <th class="cursor-pointer" wire:click="sortBy('{{ $key }}')">
                                    {{ $label }}
                                    @if ($sortColumn === $key)
                                        <span>{{ $sortDirection === 'asc' ? '🔼' : '🔽' }}</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($groupedAttendances as $attendance)
                            <tr>
                                <td>
                                    <input type="checkbox" wire:model="selectedAttendances" value="{{ $attendance['employee']['employee_id'] }}">
                                </td>
                                <td>
                                    {{ $attendance['employee']['FullName'] }}
                                    @php
                                        $hasConsensusIssue = false;
                                        foreach ($attendance['punches'] as $punchType => $punches) {
                                            foreach ($punches as $punch) {
                                                if (($punch['status'] ?? '') === 'Discrepancy') {
                                                    $hasConsensusIssue = true;
                                                    break 2;
                                                }
                                            }
                                        }
                                    @endphp
                                    @if ($hasConsensusIssue)
                                        <span class="ml-2 text-orange-500" title="Engine discrepancy - validation engines disagree on punch type assignments">🔥</span>
                                    @endif
                                    @if ($attendance['employee']['has_flexibility_issue'] ?? false)
                                        <span class="ml-2 text-yellow-500" title="Employee has 2+ unclassified punches - may need different shift schedule">⚠️</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $attendance['employee']['shift_date'] }}
                                </td>

                                @foreach (array_keys($this->getVisibleColumns()) as $type)
                                    <td>
                                        @php
                                            $punches = $attendance['punches'][$type] ?? [];
                                        @endphp

                                        @if (!empty($punches))
                                            @foreach ($punches as $punch)
                                                <div>
                                                    @php
                                                        $color = 'inherit';
                                                        if (!empty($punch['multiple']) && !empty($punch['multiples_list'])) {
                                                            $color = 'red';
                                                        } elseif (($punch['status'] ?? '') === 'Discrepancy') {
                                                            $color = 'orange';
                                                        }
                                                    @endphp
                                                    <span
                                                        x-data="{}"
                                                        x-on:click="Livewire.dispatchTo('update-time-record-modal', 'open-update-modal', {
                                                            attendanceId: '{{ $punch['attendance_id'] ?? '' }}',
                                                            employeeId: '{{ $attendance['employee']['employee_id'] }}',
                                                            deviceId: '{{ $punch['device_id'] ?? '' }}',
                                                            date: '{{ $attendance['employee']['shift_date'] }}',
                                                            punchType: '{{ $type }}',
                                                            existingTime: '{{ $punch['punch_time'] ?? '' }}',
                                                            punchState: '{{ $punch['punch_state'] ?? '' }}',
                                                            status: '{{ $punch['status'] ?? '' }}'
                                                        })"
                                                        style="color: {{ $color }}; text-decoration: underline; cursor: pointer;">
                                                        {{ is_string($punch['punch_time'] ?? null) ? $punch['punch_time'] : 'N/A' }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        @else
                                            <x-filament::button
                                                color="warning"
                                                size="xs"
                                                x-data="{}"
                                                x-on:click="Livewire.dispatchTo('create-time-record-modal', 'open-create-modal', {
                                                    employeeId: '{{ $attendance['employee']['employee_id'] }}',
                                                    date: '{{ $attendance['employee']['shift_date'] }}',
                                                    punchType: '{{ $type }}'
                                                })"
                                                style="cursor: pointer;">
                                                Input Time
                                            </x-filament::button>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="100" class="text-center" style="padding: 2rem 1rem; color: #6b7280;">
                                    Select a pay period to view attendance records.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Livewire Components -->
    <livewire:create-time-record-modal key="create-time-record-modal" />
    <livewire:update-time-record-modal key="update-time-record-modal" />
</x-filament::page>
