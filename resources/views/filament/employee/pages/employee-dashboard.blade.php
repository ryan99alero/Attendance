<x-filament-panels::page>
    @php
        $context = $this->getCurrentPunchContext();
        $canPunch = $this->canWebPunch();
        $todayPunches = $this->getTodayAttendance();
        $weekAttendance = $this->getWeekAttendance();
        $groupedByDate = $weekAttendance->groupBy('shift_date');
        $weeklyHours = $this->getWeeklyHours();
    @endphp

    {{-- Clock Header Section --}}
    <x-filament::section>
        <div style="text-align: center;">
            {{-- Digital Clock --}}
            <div
                x-data="{
                    time: '{{ now()->format('h:i:s') }}',
                    period: '{{ now()->format('A') }}',
                    init() {
                        setInterval(() => {
                            const now = new Date();
                            const hours = now.getHours();
                            const h = hours % 12 || 12;
                            const m = String(now.getMinutes()).padStart(2, '0');
                            const s = String(now.getSeconds()).padStart(2, '0');
                            this.time = `${String(h).padStart(2, '0')}:${m}:${s}`;
                            this.period = hours >= 12 ? 'PM' : 'AM';
                        }, 1000);
                    }
                }"
                style="display: flex; align-items: center; justify-content: center; gap: 8px;"
            >
                <span style="font-size: 3rem; font-weight: bold; font-family: ui-monospace, monospace; color: var(--fi-color-gray-950); color: light-dark(var(--fi-color-gray-950), white);" x-text="time"></span>
                <span style="font-size: 1.5rem; font-weight: 600; color: var(--fi-color-gray-500);" x-text="period"></span>
            </div>

            {{-- Date --}}
            <p style="margin-top: 8px; font-size: 1.125rem; color: var(--fi-color-gray-500);">
                {{ now()->format('l F j, Y') }}
            </p>

            {{-- Status Badge --}}
            <div style="margin-top: 16px;">
                @if($context['state'] === 'working')
                    <span style="display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 6px; font-size: 0.875rem; font-weight: 500; background-color: #22c55e; color: white;">
                        Current Status: IN
                    </span>
                @elseif($context['state'] === 'on_lunch')
                    <span style="display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 6px; font-size: 0.875rem; font-weight: 500; background-color: #f59e0b; color: white;">
                        Current Status: LUNCH
                    </span>
                @elseif($context['state'] === 'on_break')
                    <span style="display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 6px; font-size: 0.875rem; font-weight: 500; background-color: #6b7280; color: white;">
                        Current Status: BREAK
                    </span>
                @else
                    <span style="display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 6px; font-size: 0.875rem; font-weight: 500; background-color: #ef4444; color: white;">
                        Current Status: OUT
                    </span>
                @endif
            </div>
        </div>
    </x-filament::section>

    {{-- Punch Buttons Section --}}
    @if($canPunch)
        <x-filament::section>
            <x-slot name="heading">Quick Actions</x-slot>

            {{-- Row 1: IN / OUT --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <button
                    type="button"
                    wire:click="quickPunch(1)"
                    style="width: 100%; padding: 16px 24px; border-radius: 8px; font-weight: bold; font-size: 1.25rem; color: white; background-color: #22c55e; border: none; cursor: pointer;"
                >
                    IN
                </button>
                <button
                    type="button"
                    wire:click="quickPunch(2)"
                    style="width: 100%; padding: 16px 24px; border-radius: 8px; font-weight: bold; font-size: 1.25rem; color: white; background-color: #ef4444; border: none; cursor: pointer;"
                >
                    OUT
                </button>
            </div>

            {{-- Row 2: Lunch --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <button
                    type="button"
                    wire:click="quickPunch(3)"
                    style="width: 100%; padding: 12px 24px; border-radius: 8px; font-weight: 600; color: white; background-color: #8b5cf6; border: none; cursor: pointer;"
                >
                    Go Lunch
                </button>
                <button
                    type="button"
                    wire:click="quickPunch(4)"
                    style="width: 100%; padding: 12px 24px; border-radius: 8px; font-weight: 600; color: white; background-color: #f59e0b; border: none; cursor: pointer;"
                >
                    Return Lunch
                </button>
            </div>

            {{-- Row 3: Break --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <button
                    type="button"
                    wire:click="quickPunch(5)"
                    style="width: 100%; padding: 12px 24px; border-radius: 8px; font-weight: 600; color: #374151; background-color: #e5e7eb; border: none; cursor: pointer;"
                >
                    Go Break
                </button>
                <button
                    type="button"
                    wire:click="quickPunch(6)"
                    style="width: 100%; padding: 12px 24px; border-radius: 8px; font-weight: 600; color: white; background-color: #6b7280; border: none; cursor: pointer;"
                >
                    Return Break
                </button>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div style="text-align: center; padding: 32px;">
                <x-heroicon-o-lock-closed style="width: 48px; height: 48px; margin: 0 auto; color: #9ca3af;" />
                <p style="margin-top: 16px; color: #6b7280;">Web clock-in is not enabled for your account.</p>
                <p style="font-size: 0.875rem; color: #9ca3af;">Please contact your administrator.</p>
            </div>
        </x-filament::section>
    @endif

    {{-- Stats Widget --}}
    @livewire(\App\Filament\Employee\Widgets\EmployeeStatsWidget::class)

    {{-- Today's Entries Section --}}
    <x-filament::section>
        <x-slot name="heading">Today's Entries</x-slot>
        <x-slot name="description">{{ now()->format('M j') }}</x-slot>

        @if($todayPunches->isEmpty())
            <div style="text-align: center; padding: 24px;">
                <x-heroicon-o-clock style="width: 32px; height: 32px; margin: 0 auto; color: #9ca3af;" />
                <p style="margin-top: 8px; color: #6b7280;">No entries today</p>
            </div>
        @else
            @foreach($todayPunches as $punch)
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; {{ !$loop->last ? 'border-bottom: 1px solid rgba(156, 163, 175, 0.2);' : '' }}">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        @if($punch->punch_state === 'start')
                            <span style="display: inline-flex; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: rgba(34, 197, 94, 0.2); color: #22c55e;">
                                IN
                            </span>
                        @else
                            <span style="display: inline-flex; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                OUT
                            </span>
                        @endif
                        <span>{{ $punch->punchType?->name ?? ucfirst($punch->punch_state) }}</span>
                    </div>
                    <span style="font-family: ui-monospace, monospace; color: #6b7280;">
                        {{ \Carbon\Carbon::parse($punch->punch_time)->format('g:i A') }}
                    </span>
                </div>
            @endforeach
        @endif
    </x-filament::section>

    {{-- This Week Section --}}
    <x-filament::section>
        <x-slot name="heading">This Week</x-slot>
        <x-slot name="description">{{ \Carbon\Carbon::now()->startOfWeek()->format('M j') }} - {{ \Carbon\Carbon::now()->endOfWeek()->format('M j') }}</x-slot>

        @if($groupedByDate->isEmpty())
            <div style="text-align: center; padding: 24px;">
                <x-heroicon-o-calendar style="width: 32px; height: 32px; margin: 0 auto; color: #9ca3af;" />
                <p style="margin-top: 8px; color: #6b7280;">No entries this week</p>
            </div>
        @else
            @foreach($groupedByDate as $date => $punches)
                @php $dayHours = $this->calculateDayHours($punches); @endphp
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; {{ !$loop->last ? 'border-bottom: 1px solid rgba(156, 163, 175, 0.2);' : '' }}">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="display: inline-flex; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: rgba(107, 114, 128, 0.2); color: #9ca3af;">
                            {{ \Carbon\Carbon::parse($date)->format('D') }}
                        </span>
                        <span>{{ \Carbon\Carbon::parse($date)->format('M j') }}</span>
                    </div>
                    <span style="font-weight: 600; font-family: ui-monospace, monospace;">
                        {{ number_format($dayHours, 1) }} hrs
                    </span>
                </div>
            @endforeach

            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; margin-top: 16px; border-top: 2px solid rgba(156, 163, 175, 0.3);">
                <span style="font-weight: 500;">Total</span>
                <span style="font-size: 1.25rem; font-weight: bold; color: #3b82f6;">
                    {{ number_format($weeklyHours, 1) }} hrs
                </span>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
