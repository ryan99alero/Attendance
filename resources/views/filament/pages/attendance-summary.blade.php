<x-filament::page>
    <div>
        <!-- Filter Form -->
        <form wire:submit.prevent="updateAttendances">
            <div class="space-y-6">
                <div class="flex flex-wrap gap-4">
                    <!-- Removed duplicate search field block -->
                </div>
                {{ $this->form }}
            </div>
        </form>

        @if (!method_exists($this->form, 'hasComponent') || !$this->form->hasComponent('duplicatesFilter'))
            <div class="mt-4">
                <label for="duplicatesFilter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filter by Duplicates</label>
            </div>
        @endif

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
                        <tr wire:key="row-{{ $attendance['employee']['employee_id'] }}-{{ $attendance['employee']['shift_date'] }}">
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
                                            <div wire:key="p-{{ $punch['attendance_id'] ?? ($attendance['employee']['employee_id'].'-'.$attendance['employee']['shift_date'].'-'.$type.'-'.($punch['punch_time'] ?? 'na')) }}">
                            <span x-data
                                  @click.stop="window.Livewire.dispatch('open-update-modal', {
      attendanceId: '{{ $punch['attendance_id'] ?? '' }}',
      employeeId: '{{ $attendance['employee']['employee_id'] }}',
      deviceId: '{{ $punch['device_id'] ?? '' }}',
      date: '{{ $attendance['employee']['shift_date'] }}',
      punchType: '{{ $type }}',
      existingTime: '{{ $punch['punch_time'] ?? '' }}',
      punchState: '{{ $punch['punch_state'] ?? '' }}'
  })"
                                  style="color: {{ !empty($punch['multiple']) && !empty($punch['multiples_list']) ? 'red' : 'white' }}; text-decoration: underline; cursor: pointer;">
    {{ is_string($punch['punch_time'] ?? null) ? $punch['punch_time'] : 'N/A' }}
</span>
                                                @if (!empty($punch['multiple']) && !empty($punch['multiples_list']) && is_array($punch['multiples_list']))
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <x-filament::button type="button" class="bg-yellow-500 hover:bg-yellow-600"
                                                            x-data @click.stop="window.Livewire.dispatch('open-create-modal', {employeeId: '{{ $attendance['employee']['employee_id'] }}', date: '{{ $attendance['employee']['shift_date'] }}', punchType: '{{ $type }}'})">
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

        <!-- Livewire Components - Positioned to break out of page constraints -->
        <div class="fixed inset-0 pointer-events-none z-[90]">
            <div class="pointer-events-auto">
                <livewire:create-time-record-modal wire:key="create-time-record-modal" />
                <livewire:update-time-record-modal wire:key="update-time-record-modal" />
            </div>
        </div>
    </div>
</x-filament::page>
