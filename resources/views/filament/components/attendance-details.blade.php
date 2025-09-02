<div class="space-y-6">
    {{-- Employee Information --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">Employee Details</h3>
                <div class="mt-2 space-y-1">
                    <p class="text-sm"><span class="font-medium">Name:</span> {{ $record->employee->full_names }}</p>
                    <p class="text-sm"><span class="font-medium">Payroll ID:</span> {{ $record->employee->external_id }}</p>
                    <p class="text-sm"><span class="font-medium">Date:</span> {{ \Carbon\Carbon::parse($record->shift_date)->format('l, M j, Y') }}</p>
                </div>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">Status</h3>
                <div class="mt-2">
                    <x-filament::badge 
                        :color="match($record->status) {
                            'Migrated' => 'success',
                            'NeedsReview' => 'warning',
                            'Problem' => 'danger',
                            default => 'gray'
                        }"
                    >
                        {{ $record->status }}
                    </x-filament::badge>
                </div>
            </div>
        </div>
    </div>

    {{-- Punch Times Details --}}
    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Punch Times</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach([
                'start_time' => ['label' => 'Clock In', 'icon' => 'heroicon-m-play', 'color' => 'success'],
                'lunch_start' => ['label' => 'Lunch Start', 'icon' => 'heroicon-m-pause', 'color' => 'warning'],
                'lunch_stop' => ['label' => 'Lunch Stop', 'icon' => 'heroicon-m-play', 'color' => 'warning'],
                'stop_time' => ['label' => 'Clock Out', 'icon' => 'heroicon-m-stop', 'color' => 'danger'],
            ] as $type => $config)
                <div class="border rounded-lg p-3 {{ $punchTimes[$type] ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800' }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <x-heroicon-m-clock class="w-4 h-4 text-gray-500" />
                            <span class="font-medium text-sm">{{ $config['label'] }}</span>
                        </div>
                        @if($punchTimes[$type])
                            <span class="text-lg font-bold {{ str_contains($punchTimes[$type], 'DUP') ? 'text-red-600' : 'text-green-600' }}">
                                {{ $punchTimes[$type] }}
                            </span>
                        @else
                            <span class="text-gray-400 text-sm">Not recorded</span>
                        @endif
                    </div>

                    {{-- Show duplicate warning if needed --}}
                    @if($punchTimes[$type] && str_contains($punchTimes[$type], 'DUP'))
                        <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                            <x-heroicon-m-exclamation-triangle class="w-3 h-3 inline mr-1" />
                            Multiple punches detected for this type
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Duplicate Punches Details --}}
    @if($duplicates->isNotEmpty())
        <div>
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-red-500 mr-2" />
                Duplicate Punches Found
            </h3>
            
            @foreach($duplicates as $punchTypeId => $punchGroup)
                <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <h4 class="font-medium text-red-800 dark:text-red-300 mb-2">
                        {{ match($punchTypeId) {
                            1 => 'Clock In',
                            2 => 'Clock Out', 
                            3 => 'Lunch Start',
                            4 => 'Lunch Stop',
                            default => 'Unknown'
                        } }} - {{ $punchGroup->count() }} punches
                    </h4>
                    
                    <div class="space-y-2">
                        @foreach($punchGroup as $punch)
                            <div class="flex justify-between items-center bg-white dark:bg-gray-800 p-2 rounded border">
                                <div class="text-sm">
                                    <span class="font-medium">Time:</span> {{ \Carbon\Carbon::parse($punch->punch_time)->format('H:i:s') }}
                                    <span class="text-gray-500 ml-2">Device: {{ $punch->device_id }}</span>
                                    <span class="text-gray-500 ml-2">State: {{ $punch->punch_state }}</span>
                                </div>
                                <x-filament::button 
                                    size="xs" 
                                    color="danger"
                                    wire:click="deletePunch({{ $punch->id }})"
                                >
                                    Delete
                                </x-filament::button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Calculated Hours --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">Calculated Hours</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-600 dark:text-gray-400">Total Hours:</span>
                <span class="font-bold ml-2">
                    @php
                        $totalHours = 0;
                        if ($punchTimes['start_time'] && $punchTimes['stop_time']) {
                            $start = \Carbon\Carbon::parse($record->shift_date . ' ' . str_replace(' (DUP)', '', $punchTimes['start_time']));
                            $end = \Carbon\Carbon::parse($record->shift_date . ' ' . str_replace(' (DUP)', '', $punchTimes['stop_time']));
                            if ($end->lt($start)) $end->addDay();
                            $totalHours = $end->diffInMinutes($start) / 60;
                            
                            if ($punchTimes['lunch_start'] && $punchTimes['lunch_stop']) {
                                $lunchStart = \Carbon\Carbon::parse($record->shift_date . ' ' . str_replace(' (DUP)', '', $punchTimes['lunch_start']));
                                $lunchEnd = \Carbon\Carbon::parse($record->shift_date . ' ' . str_replace(' (DUP)', '', $punchTimes['lunch_stop']));
                                if ($lunchEnd->gt($lunchStart)) {
                                    $totalHours -= $lunchEnd->diffInMinutes($lunchStart) / 60;
                                }
                            }
                        }
                    @endphp
                    {{ $totalHours > 0 ? number_format($totalHours, 2) : 'N/A' }} hours
                </span>
            </div>
            <div>
                <span class="text-gray-600 dark:text-gray-400">Lunch Duration:</span>
                <span class="font-bold ml-2">
                    @if($punchTimes['lunch_start'] && $punchTimes['lunch_stop'])
                        @php
                            $lunchStart = \Carbon\Carbon::parse($record->shift_date . ' ' . str_replace(' (DUP)', '', $punchTimes['lunch_start']));
                            $lunchEnd = \Carbon\Carbon::parse($record->shift_date . ' ' . str_replace(' (DUP)', '', $punchTimes['lunch_stop']));
                            $lunchMinutes = $lunchEnd->gt($lunchStart) ? $lunchEnd->diffInMinutes($lunchStart) : 0;
                        @endphp
                        {{ $lunchMinutes }} minutes
                    @else
                        No lunch recorded
                    @endif
                </span>
            </div>
        </div>
    </div>

    {{-- Action Buttons --}}
    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
        <x-filament::button 
            color="success"
            icon="heroicon-m-check"
            wire:click="markAsMigrated({{ $record->employee_id }}, '{{ $record->shift_date }}')"
        >
            Mark as Migrated
        </x-filament::button>
        
        <x-filament::button 
            color="warning"
            icon="heroicon-m-pencil"
            wire:click="openEditModal({{ $record->employee_id }}, '{{ $record->shift_date }}')"
        >
            Edit Punches
        </x-filament::button>
    </div>
</div>