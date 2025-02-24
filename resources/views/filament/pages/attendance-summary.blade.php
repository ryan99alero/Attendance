<x-filament::page>
    <div>
        <!-- Filter Form -->
        <form wire:submit.prevent="updateAttendances">
            <div class="space-y-6">
                {{ $this->form }}

                <!-- Auto-Process Checkbox -->
                <div class="flex flex-col items-start space-y-4">
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="autoProcess" wire:model="autoProcess"
                               class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <label for="autoProcess" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Auto-Process
                        </label>
                    </div>
                </div>

                <!-- Process Selected Button -->
                <x-filament::button
                    wire:click="processSelected"
                    color="success"
                    class="mt-6">
                    Process Selected
                </x-filament::button>
            </div>
        </form>

        <!-- Attendance Table -->
        <div class="mt-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse border border-gray-300 dark:border-gray-700">
                    <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800">
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">
                            <input type="checkbox" wire:model="selectAll">
                        </th>
                        @foreach (['FullName' => 'Employee', 'attendance_date' => 'Date', 'FirstPunch' => 'First Punch', 'LunchStart' => 'Lunch Start', 'LunchStop' => 'Lunch Stop', 'LastPunch' => 'Last Punch'] as $field => $label)
                            <th class="border border-gray-300 px-4 py-2 dark:border-gray-700 cursor-pointer"
                                wire:click="sortBy('{{ $field }}')">
                                {{ $label }}
                                @if ($sortColumn === $field)
                                    <span>{{ $sortDirection === 'asc' ? '🔼' : '🔽' }}</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($groupedAttendances as $attendance)
                        <tr>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">
                                <input type="checkbox" wire:model="selectedAttendances"
                                       value="{{ implode(',', $attendance['attendance_ids']) }}">
                            </td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">
                                {{ $attendance['FullName'] }}
                            </td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">
                                {{ $attendance['attendance_date'] }}
                            </td>
                            @foreach (['FirstPunch' => 1, 'LunchStart' => 3, 'LunchStop' => 4, 'LastPunch' => 2] as $key => $punchType)
                                <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">
                                    @if ($attendance[$key] === null)
                                        <x-filament::button
                                            wire:click="$dispatch('open-modal', {
                                                employeeId: '{{ $attendance['employee_id'] }}',
                                                date: '{{ $attendance['attendance_date'] }}',
                                                punchType: {{ $punchType }}
                                            })"
                                            class="text-blue-500 underline">
                                            Input Time
                                        </x-filament::button>
                                    @else
                                        <span wire:click="$dispatch('open-modal', {
                                                employeeId: '{{ $attendance['employee_id'] }}',
                                                date: '{{ $attendance['attendance_date'] }}',
                                                punchType: {{ $punchType }},
                                                existingTime: '{{ $attendance[$key] }}'
                                            })"
                                              class="cursor-pointer text-blue-500 underline">
                                            {{ $attendance[$key] }}
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center border border-gray-300 px-4 py-2 dark:border-gray-700">
                                No attendance records available.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Livewire Component for the Modal -->
    <livewire:create-time-record-modal />
</x-filament::page>
