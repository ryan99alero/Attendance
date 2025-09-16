<div>
    @if ($isOpen)
        <div wire:ignore.self>
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <div class="fixed inset-0 z-50 overflow-auto flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-lg p-4 w-full max-w-md max-h-screen overflow-y-auto">
                    <h2 class="text-lg font-extrabold text-gray-700 text-center mb-4">Update Time Record</h2>

                    <form wire:submit.prevent="updateTimeRecord">

                        <div class="mb-3">
                            <label class="block text-md font-medium text-gray-700">ID</label>
                            <input
                                type="text"
                                wire:model="attendanceId"
                                class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                                readonly
                                style="color: #1a202c; background-color: #fff;">
                        </div>

                        <div class="mb-3">
                            <label class="block text-md font-medium text-gray-700">Employee</label>
                            <input
                                type="text"
                                wire:model="employeeId"
                                class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                                readonly
                                style="color: #1a202c; background-color: #fff;">
                        </div>

                        <div class="mb-3">
                            <label class="block text-md font-medium text-gray-700">Device ID</label>
                            <input
                                type="text"
                                wire:model="deviceId"
                                class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                                readonly
                                style="color: #1a202c; background-color: #fff;">
                        </div>

                        <div class="mb-3">
                            <label class="block text-md font-medium text-gray-700">Date</label>
                            <input
                                type="text"
                                wire:model="date"
                                class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                                readonly
                                style="color: #1a202c; background-color: #fff;">
                        </div>

                        <div class="mb-3">
                            <label class="block text-md font-medium text-gray-700">Punch Type</label>
                            <select
                                wire:model="punchType"
                                class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                                style="color: #1a202c; background-color: #fff;">
                                <option value="">Select Punch Type</option>
                                @foreach ($this->getPunchTypes() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('punchType') <span class="text-red-800 text-sm bg-red-100 px-2 py-1 rounded">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="block text-md font-medium text-gray-700">Punch State</label>
                            <select
                                wire:model="punchState"
                                class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                                style="color: #1a202c; background-color: #fff;">
                                <option value="">Select Punch State</option>
                                <option value="start">Start</option>
                                <option value="stop">Stop</option>
                                <option value="unknown">Unknown</option>
                            </select>
                            @error('punchState')
                            <span class="block mt-1 text-red-800 text-sm bg-red-100 px-2 py-1 rounded border border-red-300">
                                {{ $message }}
                            </span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="block text-md font-medium text-gray-700">Punch Time</label>
                            <input
                                type="time"
                                step="1"
                                wire:model="punchTime"
                                class="block w-full mt-1 border-gray-300 rounded-md text-gray-900"
                                style="color: #1a202c; background-color: #fff;">
                            @error('punchTime') <span class="text-red-800 text-sm bg-red-100 px-2 py-1 rounded">{{ $message }}</span> @enderror
                        </div>

                        @if ($status === 'Discrepancy')
                            <div class="mb-3 p-2 rounded-md" style="background-color: #fed7aa; border: 1px solid #fdba74;">
                                <p class="text-sm font-medium mb-1" style="color: #7c2d12 !important;">
                                    🔥 Engine Discrepancy - engines disagree on punch type
                                </p>
                                <p class="text-xs" style="color: #9a3412 !important;">
                                    <strong>Accept</strong> current type or <strong>Change</strong> punch type to resolve
                                </p>
                            </div>
                        @endif

                        <!-- Button Styling -->
                        @if ($status === 'Discrepancy')
                            <!-- Discrepancy Resolution Buttons -->
                            <div class="flex flex-wrap justify-between gap-2">
                                <button
                                    type="button"
                                    wire:click="closeModal"
                                    class="px-3 py-2 rounded-md border border-gray-300 text-gray-700 bg-gray-100 hover:bg-gray-200 text-sm">
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    wire:click="acceptPunchType"
                                    class="px-3 py-2 rounded-md text-white bg-orange-500 hover:bg-orange-600 text-sm">
                                    Accept Punch Type
                                </button>
                                <button
                                    type="submit"
                                    class="px-3 py-2 rounded-md border border-gray-300 text-gray-700 bg-gray-100 hover:bg-gray-200 text-sm">
                                    Accept / Update
                                </button>
                                <button
                                    type="button"
                                    wire:click="deleteTimeRecord"
                                    class="px-3 py-2 rounded-md text-white bg-red-500 hover:bg-red-600 text-sm">
                                    Delete
                                </button>
                            </div>
                        @else
                            <!-- Normal Buttons -->
                            <div class="flex justify-between">
                                <button
                                    type="button"
                                    wire:click="closeModal"
                                    class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 bg-gray-100 hover:bg-gray-200">
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 bg-gray-100 hover:bg-gray-200">
                                    Update Record
                                </button>
                                <button
                                    type="button"
                                    wire:click="deleteTimeRecord"
                                    class="px-4 py-2 rounded-md text-white"
                                    style="background-color: red; border-color: darkred;">
                                    Delete
                                </button>
                            </div>
                        @endif

                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
