<x-filament::page>
    <style>
        .punch-table {
            width: 100%;
            border-collapse: collapse;
        }
        .punch-table th,
        .punch-table td {
            border: 1px solid #d1d5db;
            padding: 0.5rem 1rem;
        }
        .punch-table thead tr {
            background-color: #f3f4f6;
        }
        .dark .punch-table th,
        .dark .punch-table td {
            border-color: #374151;
        }
        .dark .punch-table thead tr {
            background-color: #1f2937;
        }
    </style>
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
                <table class="punch-table">
                    <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Payroll ID</th>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Lunch Start</th>
                        <th>Lunch Stop</th>
                        <th>Clock Out</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($groupedPunches as $punch)
                        <tr>
                            <td>{{ $punch['FullName'] }}</td>
                            <td>{{ $punch['PayrollID'] }}</td>
                            <td>{{ $punch['PunchDate'] }}</td>
                            <td>{{ $punch['ClockIn'] ?? '' }}</td>
                            <td>{{ $punch['LunchStart'] ?? '' }}</td>
                            <td>{{ $punch['LunchStop'] ?? '' }}</td>
                            <td>{{ $punch['ClockOut'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 2rem 1rem; color: #6b7280;">
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
