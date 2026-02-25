<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>Weekly Payroll Summary</span>
                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                    {{ \Carbon\Carbon::now()->startOfWeek()->format('M j') }} - {{ \Carbon\Carbon::now()->endOfWeek()->format('M j, Y') }}
                </span>
            </div>
        </x-slot>

        @php
            $weeklyTotals = $this->getWeeklyTotals();
            $payrollData = $this->getPayrollSummaryData();
        @endphp

        {{-- Overview Statistics - SmartHR Style --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            {{-- Total Hours --}}
            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Hours</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $weeklyTotals['total_hours'] }}</p>
                        <p class="mt-1 flex items-center gap-1 text-sm">
                            <span class="text-emerald-600 dark:text-emerald-400">This week</span>
                        </p>
                    </div>
                    <div class="flex items-center justify-center rounded-full bg-primary-100 dark:bg-primary-500/20 p-3">
                        <x-filament::icon icon="heroicon-o-clock" class="text-primary-600 dark:text-primary-400" style="width: 24px; height: 24px;" />
                    </div>
                </div>
            </div>

            {{-- Gross Pay --}}
            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Gross Pay</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">${{ $weeklyTotals['total_gross_pay'] }}</p>
                        <p class="mt-1 flex items-center gap-1 text-sm">
                            <span class="text-emerald-600 dark:text-emerald-400">Estimated</span>
                        </p>
                    </div>
                    <div class="flex items-center justify-center rounded-full bg-success-100 dark:bg-success-500/20 p-3">
                        <x-filament::icon icon="heroicon-o-currency-dollar" class="text-success-600 dark:text-success-400" style="width: 24px; height: 24px;" />
                    </div>
                </div>
            </div>

            {{-- Avg Hours/Employee --}}
            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Hours/Employee</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $weeklyTotals['avg_hours_per_employee'] }}</p>
                        <p class="mt-1 flex items-center gap-1 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Per person</span>
                        </p>
                    </div>
                    <div class="flex items-center justify-center rounded-full bg-warning-100 dark:bg-warning-500/20 p-3">
                        <x-filament::icon icon="heroicon-o-chart-bar" class="text-warning-600 dark:text-warning-400" style="width: 24px; height: 24px;" />
                    </div>
                </div>
            </div>

            {{-- Active Employees --}}
            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Employees</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $weeklyTotals['employee_count'] }}</p>
                        <p class="mt-1 flex items-center gap-1 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">With time entries</span>
                        </p>
                    </div>
                    <div class="flex items-center justify-center rounded-full bg-info-100 dark:bg-info-500/20 p-3">
                        <x-filament::icon icon="heroicon-o-users" class="text-info-600 dark:text-info-400" style="width: 24px; height: 24px;" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Detailed Table --}}
        @if(count($payrollData) > 0)
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Employee</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Department</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Total</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Regular</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">OT</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Gross Pay</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($payrollData as $row)
                            <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-500/20 text-sm font-medium text-primary-700 dark:text-primary-300">
                                            {{ substr($row['first_name'] ?? '', 0, 1) }}{{ substr($row['last_name'] ?? '', 0, 1) }}
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            {{ $row['employee_name'] ?? $row['first_name'] . ' ' . $row['last_name'] }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $row['department_name'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-medium text-gray-900 dark:text-white">{{ round($row['total_hours'], 1) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-gray-600 dark:text-gray-400">{{ round($row['regular_hours'], 1) }}</td>
                                <td class="px-4 py-3 text-right font-mono">
                                    @if($row['overtime_hours'] > 0)
                                        <span class="inline-flex items-center rounded-full bg-warning-100 dark:bg-warning-500/20 px-2 py-0.5 text-xs font-semibold text-warning-700 dark:text-warning-300">
                                            {{ round($row['overtime_hours'], 1) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-success-600 dark:text-success-400">
                                    ${{ number_format($row['gross_pay'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 dark:border-gray-700 py-12">
                <div class="rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                    <x-filament::icon icon="heroicon-o-document-text" class="text-gray-400 dark:text-gray-500" style="width: 32px; height: 32px;" />
                </div>
                <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">No payroll data available</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Time entries will appear here once recorded</p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
