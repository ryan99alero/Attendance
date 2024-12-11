<x-filament::page>
    <div>
        <!-- Filter Form -->
        <form wire:submit.prevent="updatePunches">
            <div class="space-y-6">
                {{ $this->form }}
                <x-filament::button type="submit">
                    Apply Filter
                </x-filament::button>
            </div>
        </form>

        <!-- Table -->
        <div class="mt-8">
            <h2 class="text-2xl font-bold">Punch Summary</h2>
            <div class="overflow-x-auto mt-4">
                <table class="w-full table-auto border-collapse border border-gray-300 dark:border-gray-700">
                    <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800">
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Employee</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Date</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Clock In</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Lunch Start</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Lunch Stop</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Clock Out</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($groupedPunches as $punch)
                        <tr>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $punch['employee_id'] }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $punch['punch_date'] }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $punch['ClockIn'] ?? 'N/A' }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $punch['LunchStart'] ?? 'N/A' }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $punch['LunchStop'] ?? 'N/A' }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $punch['ClockOut'] ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center border border-gray-300 px-4 py-2 dark:border-gray-700">
                                No punches available.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament::page>
