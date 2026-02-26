<div class="space-y-6">
    {{-- Stats Summary Cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_recipients'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Recipients</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $stats['acknowledged'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Acknowledged</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $stats['dismissed_without_ack'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Dismissed</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $stats['pending'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Pending</div>
            </div>
        </x-filament::section>
    </div>

    {{-- Acknowledged List --}}
    @if($acknowledged->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                <span class="text-success-600 dark:text-success-400">Acknowledged</span>
            </x-slot>

            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($acknowledged as $read)
                    <li class="flex items-center justify-between py-2">
                        <span class="text-sm text-gray-900 dark:text-white">{{ $read->employee?->full_names ?? 'Unknown' }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $read->acknowledged_at?->format('M j, g:i A') }}</span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    {{-- Dismissed Without Acknowledging --}}
    @if($dismissed->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                <span class="text-warning-600 dark:text-warning-400">Dismissed Without Acknowledging</span>
            </x-slot>

            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($dismissed as $read)
                    <li class="flex items-center justify-between py-2">
                        <span class="text-sm text-gray-900 dark:text-white">{{ $read->employee?->full_names ?? 'Unknown' }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $read->dismissed_at?->format('M j, g:i A') }}</span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    {{-- Not Yet Read --}}
    @if($pendingEmployees->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                <span class="text-danger-600 dark:text-danger-400">Not Yet Read</span>
            </x-slot>

            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($pendingEmployees->take(20) as $employee)
                    <li class="py-2 text-sm text-gray-900 dark:text-white">{{ $employee->full_names }}</li>
                @endforeach
                @if($pendingEmployees->count() > 20)
                    <li class="py-2 text-sm text-gray-500 dark:text-gray-400">... and {{ $pendingEmployees->count() - 20 }} more</li>
                @endif
            </ul>
        </x-filament::section>
    @endif

    {{-- Empty State --}}
    @if($acknowledged->isEmpty() && $dismissed->isEmpty() && $pendingEmployees->isEmpty())
        <x-filament::section>
            <div class="py-6 text-center text-gray-500 dark:text-gray-400">
                No recipient data available.
            </div>
        </x-filament::section>
    @endif
</div>
