<div>
    {{-- DEBUG: Blade-only modal to verify $isOpen == true actually renders UI --}}
    @if ($isOpen)
        <div class="fixed inset-0 z-[100] bg-black/50"></div>

        <div class="fixed inset-0 z-[101] flex items-center justify-center p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Add Time Record</h2>
                    <button type="button" class="p-2" wire:click="closeModal">✕</button>
                </div>

                <div class="mb-3 text-xs text-gray-500">
                    isOpen: <strong>true</strong>
                </div>

                <div class="space-y-3 text-sm">
                    <div>Employee: <strong>{{ $employeeId }}</strong></div>
                    <div>Date: <strong>{{ $date }}</strong></div>
                    <div>Punch Type: <strong>{{ $punchType }}</strong></div>

                    <div class="flex items-center gap-2">
                        <label class="w-28">Time</label>
                        <input type="time" wire:model.defer="punchTime" class="rounded border px-2 py-1 dark:bg-gray-900">
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="w-28">State</label>
                        <select wire:model="punchState" class="rounded border px-2 py-1 dark:bg-gray-900">
                            <option value="unknown">Unknown</option>
                            <option value="start">Start</option>
                            <option value="stop">Stop</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-filament::button type="button" color="gray" wire:click="closeModal">Cancel</x-filament::button>
                    <x-filament::button type="button" color="primary" wire:click="saveTimeRecord">Save</x-filament::button>
                </div>
            </div>
        </div>
    @endif
</div>
