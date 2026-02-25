<x-filament-panels::page>
    @php
        $balance = $this->getVacationBalance();
        $availableHours = $this->getAvailableHours();
        $scheduledVacations = $this->getScheduledVacations();
        $pastVacations = $this->getPastVacations();
        $pendingRequests = $this->getPendingRequests();
        $recentRequests = $this->getRecentRequests();
    @endphp

    {{-- Balance Cards --}}
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
        {{-- Available Hours --}}
        <x-filament::section>
            <div style="text-align: center;">
                <p style="font-size: 0.875rem; font-weight: 500; color: #6b7280;">Available</p>
                <p style="font-size: 1.875rem; font-weight: bold; color: #3b82f6; margin-top: 4px;">
                    {{ number_format($availableHours, 1) }}
                </p>
                <p style="font-size: 0.75rem; color: #9ca3af; margin-top: 4px;">hours</p>
            </div>
        </x-filament::section>

        {{-- Accrued Hours --}}
        <x-filament::section>
            <div style="text-align: center;">
                <p style="font-size: 0.875rem; font-weight: 500; color: #6b7280;">Accrued</p>
                <p style="font-size: 1.875rem; font-weight: bold; color: #22c55e; margin-top: 4px;">
                    {{ number_format($balance?->accrued_hours ?? 0, 1) }}
                </p>
                <p style="font-size: 0.75rem; color: #9ca3af; margin-top: 4px;">hours</p>
            </div>
        </x-filament::section>

        {{-- Used Hours --}}
        <x-filament::section>
            <div style="text-align: center;">
                <p style="font-size: 0.875rem; font-weight: 500; color: #6b7280;">Used</p>
                <p style="font-size: 1.875rem; font-weight: bold; color: #ef4444; margin-top: 4px;">
                    {{ number_format($balance?->used_hours ?? 0, 1) }}
                </p>
                <p style="font-size: 0.75rem; color: #9ca3af; margin-top: 4px;">hours</p>
            </div>
        </x-filament::section>

        {{-- Carry Over --}}
        <x-filament::section>
            <div style="text-align: center;">
                <p style="font-size: 0.875rem; font-weight: 500; color: #6b7280;">Carry Over</p>
                <p style="font-size: 1.875rem; font-weight: bold; color: #f59e0b; margin-top: 4px;">
                    {{ number_format($balance?->carry_over_hours ?? 0, 1) }}
                </p>
                <p style="font-size: 0.75rem; color: #9ca3af; margin-top: 4px;">hours</p>
            </div>
        </x-filament::section>
    </div>

    {{-- Pending Requests Alert --}}
    @if($pendingRequests->isNotEmpty())
        <x-filament::section>
            <div style="display: flex; align-items: center; gap: 12px; background-color: rgba(251, 191, 36, 0.1); padding: 12px 16px; border-radius: 8px; border-left: 4px solid #f59e0b;">
                <x-heroicon-o-clock style="width: 24px; height: 24px; color: #f59e0b; flex-shrink: 0;" />
                <div>
                    <p style="font-weight: 600; color: #b45309;">{{ $pendingRequests->count() }} Pending Request(s)</p>
                    <p style="font-size: 0.875rem; color: #92400e;">
                        You have requests awaiting manager approval:
                        @foreach($pendingRequests as $request)
                            {{ $request->date_range }}@if(!$loop->last), @endif
                        @endforeach
                    </p>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- Request Time Off Form --}}
    <x-filament::section>
        <x-slot name="heading">Request Time Off</x-slot>
        <x-slot name="description">Submit a new vacation or time off request for manager approval</x-slot>

        <form wire:submit="submitRequest">
            {{ $this->form }}

            <div style="margin-top: 24px;">
                <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                    Submit Request
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- Three Column Layout --}}
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-top: 24px;">
        {{-- My Requests --}}
        <x-filament::section>
            <x-slot name="heading">My Requests</x-slot>
            <x-slot name="description">Recent time off requests</x-slot>

            @if($recentRequests->isEmpty())
                <div style="text-align: center; padding: 24px;">
                    <x-heroicon-o-document-text style="width: 32px; height: 32px; margin: 0 auto; color: #9ca3af;" />
                    <p style="margin-top: 8px; font-size: 0.875rem; color: #6b7280;">No requests submitted yet</p>
                </div>
            @else
                @foreach($recentRequests as $request)
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 0; {{ !$loop->last ? 'border-bottom: 1px solid rgba(156, 163, 175, 0.2);' : '' }}">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            @php
                                $statusColor = match($request->status) {
                                    'pending' => '#f59e0b',
                                    'approved' => '#22c55e',
                                    'denied' => '#ef4444',
                                    default => '#6b7280',
                                };
                                $statusBg = match($request->status) {
                                    'pending' => 'rgba(245, 158, 11, 0.2)',
                                    'approved' => 'rgba(34, 197, 94, 0.2)',
                                    'denied' => 'rgba(239, 68, 68, 0.2)',
                                    default => 'rgba(107, 114, 128, 0.2)',
                                };
                                $statusIcon = match($request->status) {
                                    'pending' => 'heroicon-s-clock',
                                    'approved' => 'heroicon-s-check-circle',
                                    'denied' => 'heroicon-s-x-circle',
                                    default => 'heroicon-s-question-mark-circle',
                                };
                            @endphp
                            <span style="display: flex; width: 32px; height: 32px; align-items: center; justify-content: center; border-radius: 8px; background-color: {{ $statusBg }};">
                                <x-dynamic-component :component="$statusIcon" style="width: 16px; height: 16px; color: {{ $statusColor }};" />
                            </span>
                            <div>
                                <span style="font-size: 0.875rem; font-weight: 500;">
                                    {{ $request->date_range }}
                                </span>
                                <span style="display: block; font-size: 0.75rem; color: {{ $statusColor }}; text-transform: capitalize;">
                                    {{ $request->status }}
                                </span>
                            </div>
                        </div>
                        <span style="font-size: 0.75rem; color: #6b7280;">
                            {{ $request->hours_requested }} hrs
                        </span>
                    </div>
                @endforeach
            @endif
        </x-filament::section>

        {{-- Upcoming Vacation --}}
        <x-filament::section>
            <x-slot name="heading">Upcoming Time Off</x-slot>
            <x-slot name="description">Approved scheduled dates</x-slot>

            @if($scheduledVacations->isEmpty())
                <div style="text-align: center; padding: 24px;">
                    <x-heroicon-o-calendar style="width: 32px; height: 32px; margin: 0 auto; color: #9ca3af;" />
                    <p style="margin-top: 8px; font-size: 0.875rem; color: #6b7280;">No upcoming time off scheduled</p>
                </div>
            @else
                @foreach($scheduledVacations as $vacation)
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 0; {{ !$loop->last ? 'border-bottom: 1px solid rgba(156, 163, 175, 0.2);' : '' }}">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="display: flex; width: 32px; height: 32px; align-items: center; justify-content: center; border-radius: 8px; background-color: rgba(59, 130, 246, 0.2);">
                                <x-heroicon-s-sun style="width: 16px; height: 16px; color: #3b82f6;" />
                            </span>
                            <div>
                                <span style="font-size: 0.875rem; font-weight: 500;">
                                    {{ \Carbon\Carbon::parse($vacation->vacation_date)->format('l, M j, Y') }}
                                </span>
                                @if($vacation->is_half_day)
                                    <span style="margin-left: 8px; font-size: 0.75rem; color: #f59e0b;">(Half Day)</span>
                                @endif
                            </div>
                        </div>
                        <span style="font-size: 0.75rem; color: #6b7280;">
                            {{ \Carbon\Carbon::parse($vacation->vacation_date)->diffForHumans() }}
                        </span>
                    </div>
                @endforeach
            @endif
        </x-filament::section>

        {{-- Past Vacation --}}
        <x-filament::section>
            <x-slot name="heading">Recent Time Off</x-slot>
            <x-slot name="description">This year</x-slot>

            @if($pastVacations->isEmpty())
                <div style="text-align: center; padding: 24px;">
                    <x-heroicon-o-clock style="width: 32px; height: 32px; margin: 0 auto; color: #9ca3af;" />
                    <p style="margin-top: 8px; font-size: 0.875rem; color: #6b7280;">No time off taken yet this year</p>
                </div>
            @else
                @foreach($pastVacations as $vacation)
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 0; {{ !$loop->last ? 'border-bottom: 1px solid rgba(156, 163, 175, 0.2);' : '' }}">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="display: flex; width: 32px; height: 32px; align-items: center; justify-content: center; border-radius: 8px; background-color: rgba(107, 114, 128, 0.2);">
                                <x-heroicon-s-check style="width: 16px; height: 16px; color: #22c55e;" />
                            </span>
                            <span style="font-size: 0.875rem; color: #9ca3af;">
                                {{ \Carbon\Carbon::parse($vacation->vacation_date)->format('M j, Y') }}
                            </span>
                        </div>
                        <span style="font-size: 0.75rem; color: #6b7280;">
                            {{ $vacation->is_half_day ? '4 hrs' : '8 hrs' }}
                        </span>
                    </div>
                @endforeach
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
