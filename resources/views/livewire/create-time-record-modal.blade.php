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
                        <input
                            type="text"
                            wire:model="employeeId"
                            class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                            readonly
                            style="color: #1a202c; background-color: #fff;">
                    </div>

                    <div class="mb-4">
                        <label class="block text-md font-medium text-gray-700">Date</label>
                        <input
                            type="text"
                            wire:model="date"
                            class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                            readonly
                            style="color: #1a202c; background-color: #fff;">
                    </div>

                    <div class="mb-4">
                        <label class="block text-md font-medium text-gray-700">Punch Type</label>
                        <select
                            wire:model="punchType"
                            class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                            style="color: #1a202c; background-color: #fff;">
                            <option value="">Select Punch Type</option>
                            <option value="1">Clock In</option>
                            <option value="2">Clock Out</option>
                            <option value="3">Lunch Start</option>
                            <option value="4">Lunch Stop</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-md font-medium text-gray-700">Punch Time</label>
                        <input
                            type="time"
                            wire:model="punchTime"
                            class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                            style="color: #1a202c; background-color: #fff;">
                    </div>

                    <!-- Button Styling -->
                    <div class="flex justify-between">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 bg-gray-100 hover:bg-gray-200">
                            Cancel
                        </button>
                        <button
                            type="button"
                            wire:click="saveTimeRecord"
                            class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 bg-gray-100 hover:bg-gray-200">
                            Save Record
                        </button>
                    </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
