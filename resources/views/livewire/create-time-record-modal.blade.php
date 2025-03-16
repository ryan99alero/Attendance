<div>
    @if ($isOpen)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="fixed inset-0 z-50 overflow-auto flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-lg p-6 w-96">
                <h2 class="text-lg font-extrabold text-gray-700 text-center">Create Time Record</h2>
                <br>

                <form wire:submit.prevent="saveTimeRecord">
                    <div class="mb-4">
                        <label class="block text-md font-medium text-gray-700">Employee</label>
                        <input type="text" wire:model="employeeId"
                               class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                               readonly style="color: #1a202c; background-color: #fff;">
                    </div>

                    <div class="mb-4">
                        <label class="block text-md font-medium text-gray-700">Date</label>
                        <input type="text" wire:model="date"
                               class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                               readonly style="color: #1a202c; background-color: #fff;">
                    </div>

                    <div class="mb-4">
                        <label class="block text-md font-medium text-gray-700">Punch Type</label>
                        <select wire:model="punchType"
                                class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                                style="color: #1a202c; background-color: #fff;">
                            <option value="">Select Punch Type</option>
                            <option value="start_time">Clock In</option>
                            <option value="stop_time">Clock Out</option>
                            <option value="lunch_start">Lunch Start</option>
                            <option value="lunch_stop">Lunch Stop</option>
                        </select>
                    </div>

                    <!-- ✅ Single Punch State Field -->
                    <div class="mb-4">
                        <label class="block text-md font-medium text-gray-700">Punch State</label>
                        <select wire:model="punchState"
                                class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                                style="color: #1a202c; background-color: #fff;">
                            <option value="start">Start</option>
                            <option value="stop">Stop</option>
                        </select>
                        @error('punchState')
                        <p class="mt-1 text-sm font-semibold" style="color: red; border-color: darkred;">
                            {{ $message }}
                        </p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-md font-medium text-gray-700">Punch Time</label>
                        <input type="time" wire:model="punchTime"
                               class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                               style="color: #1a202c; background-color: #fff;">
                    </div>

                    <div class="flex justify-between">
                        <button type="button" wire:click="closeModal"
                                class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 bg-gray-100 hover:bg-gray-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 bg-gray-100 hover:bg-gray-200">
                            Save Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
