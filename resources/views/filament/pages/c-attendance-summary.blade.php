<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Actions --}}
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ $this->getTitle() }}
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Modern Filament v4 implementation with advanced filtering and table features
                </p>
            </div>
        </div>

        {{-- Filters Form --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            {{ $this->form }}
        </div>

        {{-- Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ $this->getTableQuery()->count() }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Records</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                        {{ $this->getTableQuery()->where('status', '!=', 'Migrated')->count() }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Problem Records</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ $this->getTableQuery()->where('status', 'Migrated')->count() }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Migrated Records</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                        {{ count($this->getDuplicateAttendanceIds()) }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Duplicate Punches</div>
                </div>
            </x-filament::section>
        </div>

        {{-- Main Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            {{ $this->table }}
        </div>

        {{-- Quick Actions Bar --}}
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
            <div class="flex flex-wrap gap-3">
                <x-filament::button 
                    color="success" 
                    icon="heroicon-m-check"
                    wire:click="$dispatch('bulk-action', { action: 'mark_migrated' })"
                >
                    Quick Mark as Migrated
                </x-filament::button>

                <x-filament::button 
                    color="warning" 
                    icon="heroicon-m-exclamation-triangle"
                    wire:click="$dispatch('filter-problems')"
                >
                    Show Problems Only
                </x-filament::button>

                <x-filament::button 
                    color="info" 
                    icon="heroicon-m-document-duplicate"
                    wire:click="$dispatch('filter-duplicates')"
                >
                    Show Duplicates Only
                </x-filament::button>

                <x-filament::button 
                    color="gray" 
                    icon="heroicon-m-arrow-path"
                    wire:click="resetTableCache"
                >
                    Refresh
                </x-filament::button>
            </div>
        </div>

        {{-- Footer Info --}}
        <div class="text-xs text-gray-500 dark:text-gray-400 text-center">
            Built with Filament v4 • Features: Live validation, Advanced filtering, Bulk actions, Modern UI
        </div>
    </div>

    {{-- Custom JavaScript for enhanced interactions --}}
    @script
    <script>
        // Enhanced table interactions
        $wire.on('filter-problems', () => {
            $wire.set('data.status_filter', 'problem');
        });

        $wire.on('filter-duplicates', () => {
            $wire.set('data.duplicates_filter', 'duplicates_only');
        });

        // Auto-save form state to localStorage
        document.addEventListener('livewire:updated', () => {
            const formData = $wire.get('data');
            localStorage.setItem('c_attendance_summary_filters', JSON.stringify(formData));
        });

        // Restore form state from localStorage on page load
        document.addEventListener('DOMContentLoaded', () => {
            const savedFilters = localStorage.getItem('c_attendance_summary_filters');
            if (savedFilters) {
                try {
                    const filters = JSON.parse(savedFilters);
                    // Only restore if form is empty (initial load)
                    if (!$wire.get('data.pay_period_id')) {
                        $wire.set('data', filters);
                    }
                } catch (e) {
                    console.log('Could not restore saved filters:', e);
                }
            }
        });
    </script>
    @endscript
</x-filament-panels::page>