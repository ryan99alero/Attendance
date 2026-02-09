<div>
    @if ($isOpen)
        <!-- Modal Backdrop -->
        <div style="position: fixed; inset: 0; background-color: rgba(107, 114, 128, 0.75); z-index: 9998;"></div>

        <!-- Modal Container -->
        <div style="position: fixed; inset: 0; z-index: 9999; overflow: auto; display: flex; align-items: center; justify-content: center; padding: 1rem;">
            <div style="background-color: #fff; border-radius: 0.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); padding: 1rem; width: 100%; max-width: 28rem; max-height: 100vh; overflow-y: auto;">
                <h2 style="font-size: 1.125rem; font-weight: 800; color: #374151; text-align: center; margin-bottom: 1rem;">Update Time Record</h2>

                <form wire:submit.prevent="updateTimeRecord">

                    <div style="margin-bottom: 0.75rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151;">ID</label>
                        <input
                            type="text"
                            wire:model="attendanceId"
                            readonly
                            style="display: block; width: 100%; margin-top: 0.25rem; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; color: #1a202c; background-color: #f9fafb;">
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151;">Employee</label>
                        <input
                            type="text"
                            wire:model="employeeId"
                            readonly
                            style="display: block; width: 100%; margin-top: 0.25rem; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; color: #1a202c; background-color: #f9fafb;">
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151;">Device ID</label>
                        <input
                            type="text"
                            wire:model="deviceId"
                            readonly
                            style="display: block; width: 100%; margin-top: 0.25rem; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; color: #1a202c; background-color: #f9fafb;">
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151;">Date</label>
                        <input
                            type="text"
                            wire:model="date"
                            readonly
                            style="display: block; width: 100%; margin-top: 0.25rem; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; color: #1a202c; background-color: #f9fafb;">
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151;">Punch Type</label>
                        <select
                            wire:model="punchType"
                            style="display: block; width: 100%; height: 42px; margin-top: 0.25rem; padding: 0.5rem 2rem 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; color: #1a202c; background-color: #fff; font-size: 0.875rem; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%236b7280%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.5rem center; background-size: 1.25rem; -webkit-appearance: none; -moz-appearance: none; appearance: none; cursor: pointer;">
                            <option value="">Select Punch Type</option>
                            @foreach ($this->getPunchTypes() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('punchType') <span style="display: block; margin-top: 0.25rem; font-size: 0.75rem; color: #991b1b; background-color: #fee2e2; padding: 0.25rem 0.5rem; border-radius: 0.25rem;">{{ $message }}</span> @enderror
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151;">Punch State</label>
                        <select
                            wire:model="punchState"
                            style="display: block; width: 100%; height: 42px; margin-top: 0.25rem; padding: 0.5rem 2rem 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; color: #1a202c; background-color: #fff; font-size: 0.875rem; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%236b7280%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.5rem center; background-size: 1.25rem; -webkit-appearance: none; -moz-appearance: none; appearance: none; cursor: pointer;">
                            <option value="">Select Punch State</option>
                            <option value="start">Start</option>
                            <option value="stop">Stop</option>
                            <option value="unknown">Unknown</option>
                        </select>
                        @error('punchState')
                        <span style="display: block; margin-top: 0.25rem; font-size: 0.75rem; color: #991b1b; background-color: #fee2e2; padding: 0.25rem 0.5rem; border-radius: 0.25rem; border: 1px solid #fca5a5;">
                            {{ $message }}
                        </span>
                        @enderror
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151;">Punch Time</label>
                        <input
                            type="time"
                            step="1"
                            wire:model="punchTime"
                            style="display: block; width: 100%; margin-top: 0.25rem; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; color: #1a202c; background-color: #fff;">
                        @error('punchTime') <span style="display: block; margin-top: 0.25rem; font-size: 0.75rem; color: #991b1b; background-color: #fee2e2; padding: 0.25rem 0.5rem; border-radius: 0.25rem;">{{ $message }}</span> @enderror
                    </div>

                    @if ($status === 'Discrepancy')
                        <div style="margin-bottom: 0.75rem; padding: 0.5rem; border-radius: 0.375rem; background-color: #fed7aa; border: 1px solid #fdba74;">
                            <p style="font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; color: #7c2d12;">
                                🔥 Engine Discrepancy - engines disagree on punch type
                            </p>
                            <p style="font-size: 0.75rem; color: #9a3412;">
                                <strong>Accept</strong> current type or <strong>Change</strong> punch type to resolve
                            </p>
                        </div>
                    @endif

                    <!-- Button Styling -->
                    @if ($status === 'Discrepancy')
                        <!-- Discrepancy Resolution Buttons -->
                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem;">
                            <button
                                type="button"
                                wire:click="closeModal"
                                style="padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #d1d5db; color: #374151; background-color: #f3f4f6; cursor: pointer; font-size: 0.875rem;">
                                Cancel
                            </button>
                            <button
                                type="button"
                                wire:click="acceptPunchType"
                                style="padding: 0.5rem 0.75rem; border-radius: 0.375rem; color: #fff; background-color: #f97316; cursor: pointer; font-size: 0.875rem; border: none;">
                                Accept Punch Type
                            </button>
                            <button
                                type="submit"
                                style="padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #d1d5db; color: #374151; background-color: #f3f4f6; cursor: pointer; font-size: 0.875rem;">
                                Accept / Update
                            </button>
                            <button
                                type="button"
                                wire:click="deleteTimeRecord"
                                style="padding: 0.5rem 0.75rem; border-radius: 0.375rem; color: #fff; background-color: #ef4444; cursor: pointer; font-size: 0.875rem; border: none;">
                                Delete
                            </button>
                        </div>
                    @else
                        <!-- Normal Buttons -->
                        <div style="display: flex; justify-content: space-between; gap: 0.5rem;">
                            <button
                                type="button"
                                wire:click="closeModal"
                                style="padding: 0.5rem 1rem; border-radius: 0.375rem; border: 1px solid #d1d5db; color: #374151; background-color: #f3f4f6; cursor: pointer;">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                style="padding: 0.5rem 1rem; border-radius: 0.375rem; border: 1px solid #d1d5db; color: #374151; background-color: #f3f4f6; cursor: pointer;">
                                Update Record
                            </button>
                            <button
                                type="button"
                                wire:click="deleteTimeRecord"
                                style="padding: 0.5rem 1rem; border-radius: 0.375rem; color: #fff; background-color: #ef4444; cursor: pointer; border: none;">
                                Delete
                            </button>
                        </div>
                    @endif

                </form>
            </div>
        </div>
    @endif
</div>
