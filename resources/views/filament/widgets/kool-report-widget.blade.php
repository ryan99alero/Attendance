<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Weekly Payroll Summary (KoolReport Integration)
        </x-slot>

        @php
            $weeklyTotals = $this->getWeeklyTotals();
            $payrollData = $this->getPayrollSummaryData();
        @endphp

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-700">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100">{{ $weeklyTotals['total_hours'] }}</h3>
                        <p class="text-sm text-blue-700 dark:text-blue-300">Total Hours</p>
                    </div>
                </div>
            </div>

            <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-700">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-green-900 dark:text-green-100">${{ $weeklyTotals['total_gross_pay'] }}</h3>
                        <p class="text-sm text-green-700 dark:text-green-300">Gross Pay</p>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-200 dark:border-yellow-700">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-yellow-900 dark:text-yellow-100">{{ $weeklyTotals['avg_hours_per_employee'] }}</h3>
                        <p class="text-sm text-yellow-700 dark:text-yellow-300">Avg Hours/Employee</p>
                    </div>
                </div>
            </div>

            <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg border border-purple-200 dark:border-purple-700">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-purple-900 dark:text-purple-100">{{ $weeklyTotals['employee_count'] }}</h3>
                        <p class="text-sm text-purple-700 dark:text-purple-300">Active Employees</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Table -->
        @if(count($payrollData) > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">Employee</th>
                        <th scope="col" class="px-6 py-3">Department</th>
                        <th scope="col" class="px-6 py-3">Shift Date</th>
                        <th scope="col" class="px-6 py-3">Total Hours</th>
                        <th scope="col" class="px-6 py-3">Regular Hours</th>
                        <th scope="col" class="px-6 py-3">OT Hours</th>
                        <th scope="col" class="px-6 py-3">Gross Pay</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payrollData as $row)
                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                            {{ $row['employee_name'] ?? $row['first_name'] . ' ' . $row['last_name'] }}
                        </td>
                        <td class="px-6 py-4">{{ $row['department_name'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4">{{ \Carbon\Carbon::parse($row['shift_date'])->format('M j, Y') }}</td>
                        <td class="px-6 py-4">{{ round($row['total_hours'], 1) }}</td>
                        <td class="px-6 py-4">{{ round($row['regular_hours'], 1) }}</td>
                        <td class="px-6 py-4 {{ $row['overtime_hours'] > 0 ? 'text-orange-600 dark:text-orange-400 font-semibold' : '' }}">
                            {{ round($row['overtime_hours'], 1) }}
                        </td>
                        <td class="px-6 py-4 font-semibold text-green-600 dark:text-green-400">
                            ${{ number_format($row['gross_pay'], 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-8">
            <div class="text-gray-400 dark:text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-lg">No payroll data available for this week</p>
                <p class="text-sm mt-2">Data is processed via KoolReport integration</p>
            </div>
        </div>
        @endif

        <!-- Footer Info -->
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                <span>Data processed via KoolReport • Week of {{ \Carbon\Carbon::now()->startOfWeek()->format('M j') }} - {{ \Carbon\Carbon::now()->endOfWeek()->format('M j, Y') }}</span>
                <span>Last updated: {{ \Carbon\Carbon::now()->format('g:i A') }}</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>