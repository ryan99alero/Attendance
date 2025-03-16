<x-filament::page>
    <div>
        <!-- Filter Form -->
        <form wire:submit.prevent="updateAttendances">
            <div class="space-y-6">
                {{ $this->form }}

                <x-filament::button wire:click="processSelected" color="success" class="mt-6">
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
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Employee</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Shift Date</th>
                        @foreach (['start_time' => 'Clock In', 'lunch_start' => 'Lunch Start', 'lunch_stop' => 'Lunch Stop', 'stop_time' => 'Clock Out', 'unclassified' => 'Unclassified'] as $key => $label)
                            <th class="border border-gray-300 px-4 py-2 dark:border-gray-700 cursor-pointer"
                                wire:click="sortBy('{{ $key }}')">
                                {{ $label }}
                                @if ($sortColumn === $key)
                                    <span>{{ $sortDirection === 'asc' ? '🔼' : '🔽' }}</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($groupedAttendances as $attendance)
                        <tr>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">
                                <input type="checkbox" wire:model="selectedAttendances" value="{{ $attendance['employee']['employee_id'] }}">
                            </td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">
                                {{ $attendance['employee']['FullName'] }}
                            </td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">
                                {{ $attendance['employee']['shift_date'] }}
                            </td>

                            @foreach (['start_time', 'lunch_start', 'lunch_stop', 'stop_time', 'unclassified'] as $type)
                                <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">
                                    @php
                                        $punches = $attendance['punches'][$type] ?? [];
                                    @endphp

                                    @if (!empty($punches))
                                        @foreach ($punches as $punch)
                                            <div>
                            <span x-data
                                  @click="
                                        $dispatch('open-update-modal', {
                                            attendanceId: '{{ $punch['attendance_id'] ?? '' }}',
                                            employeeId: '{{ $attendance['employee']['employee_id'] }}',
                                            deviceId: '{{ $punch['device_id'] ?? '' }}',
                                            date: '{{ $attendance['employee']['shift_date'] }}',
                                            punchType: '{{ $type }}',
                                            existingTime: '{{ $punch['punch_time'] ?? '' }}',
                                            punchState: '{{ $punch['punch_state'] ?? '' }}'
                                        })"
                                  class="cursor-pointer text-blue-500 underline">
                                {{ $punch['punch_time'] ?? 'N/A' }}
                            </span>

                                                @if ($punch['multiple'])
                                                    <span class="ml-2 text-red-600 font-bold">(Multiple: {{ implode(', ', $punch['multiples_list']) }})</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <x-filament::button class="bg-yellow-500 hover:bg-yellow-600"
                                                            x-data @click="$dispatch('open-create-modal', {employeeId: '{{ $attendance['employee']['employee_id'] }}', date: '{{ $attendance['employee']['shift_date'] }}', punchType: '{{ $type }}'})">
                                            Input Time
                                        </x-filament::button>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Livewire Components -->
    <livewire:create-time-record-modal />
    <livewire:update-time-record-modal />
</x-filament::page>
