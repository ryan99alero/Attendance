<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ADP Export Sample</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Include KoolReport Filament theme integration -->
    <link rel="stylesheet" href="{{ asset('css/koolreport-filament-theme.css') }}">
    <script>
        // Configure Tailwind to support dark mode
        tailwind.config = {
            darkMode: 'class'
        }

        // Theme detection and synchronization with Filament
        function detectAndApplyTheme() {
            let theme = localStorage.getItem('theme') || 'system';
            let isDark = false;

            if (theme === 'dark') {
                isDark = true;
            } else if (theme === 'system') {
                isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            }

            if (isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }

        detectAndApplyTheme();
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', detectAndApplyTheme);
        window.addEventListener('storage', function(e) {
            if (e.key === 'theme') {
                detectAndApplyTheme();
            }
        });
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 transition-colors duration-200">
    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">ADP Export Sample</h1>
            <p class="text-gray-600 dark:text-gray-400">Sample format of the ADP-compatible export file</p>
        </div>

        <!-- Sample Data Info -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Sample Data Format</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>This shows the format of data that will be exported for ADP payroll import. The actual export will contain real attendance data based on your selected date range and filters.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sample Data Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Sample Export Data</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">CSV format with headers as shown below</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            @if(!empty($sampleData))
                                @foreach(array_keys($sampleData[0]) as $header)
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ $header }}
                                    </th>
                                @endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($sampleData as $row)
                            <tr class="hover:bg-gray-50">
                                @foreach($row as $value)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $value }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                    No sample data available
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Field Descriptions -->
        <div class="mt-8 bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Field Descriptions</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-3">
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Employee_ID</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Unique identifier for the employee (external_id from employees table)</p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Employee_Name</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Full name of the employee</p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Date</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Work date in MM/DD/YYYY format</p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Time</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Punch time in HH:MM format (24-hour)</p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Hours</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Regular hours worked for the day</p>
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">OT_Hours</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Overtime hours (hours over 8 per day)</p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Pay_Code</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Pay code classification (REG for regular, OT for overtime, etc.)</p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Cost_Center</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Department code for cost allocation</p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Department</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Full department name</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customization Info -->
        <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <h2 class="text-lg font-medium text-yellow-800 mb-4">Customization Options</h2>
            <div class="space-y-3 text-sm text-yellow-700">
                <p><strong>Field Mapping:</strong> Column names can be customized to match your ADP import requirements.</p>
                <p><strong>Static Values:</strong> Add fields with static values (like company codes or default pay types).</p>
                <p><strong>Calculated Fields:</strong> Create computed fields based on existing data (like gross pay calculations).</p>
                <p><strong>Filtering:</strong> Export can be filtered by date range, employees, departments, or pay codes.</p>
                <p><strong>Formats:</strong> Available in CSV, Excel, JSON, and XML formats.</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-8 flex justify-between">
            <a href="{{ route('reports.dashboard') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path>
                </svg>
                Back to Dashboard
            </a>

            <a href="/admin/reports"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Generate Real Export
                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    </div>
</body>
</html>