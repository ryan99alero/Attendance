<x-filament::page>
    <div>
        <!-- Filter Form -->processSelected
        <form wire:submit.prevent="updateAttendances">
            <div class="flex items-center justify-between space-x-4">
                {{ $this->form }}

                <!-- Process Selected Button -->
                <x-filament::button
                    wire:click="processSelected"
                    color="success">
                    Process Selected
                </x-filament::button>
            </div>
        </form>

        <!-- Table -->
        <div class="mt-8">
            <h2 class="text-2xl font-bold">Attendance Summary</h2>
            <div class="overflow-x-auto mt-4">
                <table class="w-full table-auto border-collapse border border-gray-300 dark:border-gray-700">
                    <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800">
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">
                            <input type="checkbox" wire:model="selectAll">
                        </th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Employee</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Date</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">First Punch</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Lunch Start</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Lunch Stop</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Last Punch</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($groupedAttendances as $attendance)
                        <tr>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">
                                <input type="checkbox" wire:model="selectedAttendances" value="{{ implode(',', $attendance['attendance_ids']) }}">
                            </td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['FullName'] }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['attendance_date'] }}</td>
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
                                        {{ $attendance[$key] }}
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
