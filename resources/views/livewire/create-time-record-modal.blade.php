<div
    x-data="{ open: @entangle('isOpen') }"
    x-cloak
>
    <!-- Backdrop -->
    <div
        x-show="open"
        class="fixed inset-0 z-40 bg-black/50"
        aria-hidden="true"
    ></div>

    <!-- Modal -->
    <div
        x-show="open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Add Time Record</h2>
                <button type="button" class="text-gray-500 hover:text-gray-700" @click="open = false" wire:click="closeModal">✕</button>
            </div>

            <div class="space-y-4">
                {{-- Employee --}}
                <div>
                    <label class="block text-sm font-medium mb-1">Employee ID</label>
                    <input type="text" class="w-full rounded-md border-gray-300 dark:bg-gray-800"
                           wire:model="employeeId" />
                    @error('employeeId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Date --}}
                <div>
                    <label class="block text-sm font-medium mb-1">Date</label>
                    <input type="date" class="w-full rounded-md border-gray-300 dark:bg-gray-800"
                           wire:model="date" />
                    @error('date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Punch Type --}}
                <div>
                    <label class="block text-sm font-medium mb-1">Punch Type</label>
                    <select class="w-full rounded-md border-gray-300 dark:bg-gray-800"
                            wire:model.live="punchType"
                            wire:change="updatedPunchType">
                        <option value="">Select…</option>
                        <option value="start_time">Clock In</option>
                        <option value="lunch_start">Lunch Start</option>
                        <option value="lunch_stop">Lunch Stop</option>
                        <option value="stop_time">Clock Out</option>
                        <option value="unclassified">Unclassified</option>
                    </select>
                    @error('punchType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Punch Time --}}
                <div>
                    <label class="block text-sm font-medium mb-1">Punch Time (HH:MM)</label>
                    <input type="time" step="60" class="w-full rounded-md border-gray-300 dark:bg-gray-800"
                           wire:model="punchTime" />
                    @error('punchTime') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Punch State --}}
                <div>
                    <label class="block text-sm font-medium mb-2">Punch State</label>
                    <div class="flex gap-4">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" value="start" wire:model="punchState"> <span>Start</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" value="stop" wire:model="punchState"> <span>Stop</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" value="unknown" wire:model="punchState"> <span>Unknown</span>
                        </label>
                    </div>
                    @error('punchState') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="rounded-md border px-3 py-2" @click="open = false" wire:click="closeModal">
                    Cancel
                </button>
                <button type="button" class="rounded-md bg-amber-600 px-3 py-2 text-white hover:bg-amber-700"
                        wire:click="saveTimeRecord" wire:target="saveTimeRecord" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveTimeRecord">Save</span>
                    <span wire:loading wire:target="saveTimeRecord">Saving…</span>
                </button>
            </div>
        </div>
    </div>
</div>
