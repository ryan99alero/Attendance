<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Info Card -->
        <x-filament::section>
            <x-slot name="heading">
                🏖️ Vacation Accrual Processing
            </x-slot>

            <x-slot name="description">
                Process anniversary-based vacation accruals for employees. The system automatically runs daily at 3:00 AM, but you can manually process accruals here.
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-blue-900">🗓️ Scheduled Processing</h3>
                    <p class="text-sm text-blue-700 mt-1">Runs daily at 3:00 AM automatically</p>
                </div>

                <div class="bg-green-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-green-900">✅ Safety Checks</h3>
                    <p class="text-sm text-green-700 mt-1">Prevents duplicate processing</p>
                </div>

                <div class="bg-purple-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-purple-900">🔄 Catch-up Processing</h3>
                    <p class="text-sm text-purple-700 mt-1">Handles missed anniversaries</p>
                </div>
            </div>
        </x-filament::section>

        <!-- Command Examples -->
        <x-filament::section>
            <x-slot name="heading">
                📝 Command Line Usage
            </x-slot>

            <div class="space-y-3">
                <div class="bg-gray-50 p-3 rounded-lg font-mono text-sm">
                    <strong>Process all employees today:</strong><br>
                    <code class="text-blue-600">php artisan vacation:process-accruals</code>
                </div>

                <div class="bg-gray-50 p-3 rounded-lg font-mono text-sm">
                    <strong>Dry run for specific date:</strong><br>
                    <code class="text-blue-600">php artisan vacation:process-accruals --date=2024-01-15 --dry-run</code>
                </div>

                <div class="bg-gray-50 p-3 rounded-lg font-mono text-sm">
                    <strong>Process specific employee:</strong><br>
                    <code class="text-blue-600">php artisan vacation:process-accruals --employee=123</code>
                </div>

                <div class="bg-gray-50 p-3 rounded-lg font-mono text-sm">
                    <strong>Force reprocessing:</strong><br>
                    <code class="text-blue-600">php artisan vacation:process-accruals --force</code>
                </div>
            </div>
        </x-filament::section>

        @if(session('vacation_processing_output'))
            <!-- Processing Output -->
            <x-filament::section>
                <x-slot name="heading">
                    🖥️ Last Processing Output
                </x-slot>

                <div class="bg-black text-green-400 p-4 rounded-lg font-mono text-sm whitespace-pre-wrap overflow-x-auto">{{ session('vacation_processing_output') }}</div>
            </x-filament::section>
        @endif

        <!-- Recent Accrual Transactions Table -->
        <x-filament::section>
            <x-slot name="heading">
                📊 Recent Vacation Accrual Transactions
            </x-slot>

            <x-slot name="description">
                View the most recent vacation accrual transactions processed by the system.
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
