<x-filament::page>
    <div>
        <!-- Filter Form -->
        <form wire:submit.prevent="updateAttendances">
            <div class="space-y-6">
                {{ $this->form }}
                <x-filament::button type="submit">
                    Apply Filter
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
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Employee ID</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Full Name</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Payroll ID</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Date</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">First Punch</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Lunch Start</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Lunch Stop</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Last Punch</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Manual Entries</th>
                        <th class="border border-gray-300 px-4 py-2 dark:border-gray-700">Total Punches</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($groupedAttendances as $attendance)
                        <tr>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['employee_id'] }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['FullName'] }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['PayrollID'] }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['attendance_date'] }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['FirstPunch'] ?? 'N/A' }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['LunchStart'] ?? 'N/A' }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['LunchStop'] ?? 'N/A' }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['LastPunch'] ?? 'N/A' }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['ManualEntries'] }}</td>
                            <td class="border border-gray-300 px-4 py-2 dark:border-gray-700">{{ $attendance['TotalPunches'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center border border-gray-300 px-4 py-2 dark:border-gray-700">
                                No attendance records available.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament::page>
