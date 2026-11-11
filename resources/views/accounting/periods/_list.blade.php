@php $tones = ['open' => 'success', 'locked' => 'warning', 'closed' => 'neutral']; @endphp
<div class="space-y-6">
    @foreach ($years as $year)
        @php
            $ended = $year->end_date->lt($today);
            $olderOpen = $years->contains(fn ($y) => $y->status === 'open' && $y->start_date->lt($year->start_date));
            $newerClosed = $years->contains(fn ($y) => $y->status === 'closed' && $y->start_date->gt($year->start_date));
        @endphp
        <x-card :title="$year->name" :padded="false">
            <x-slot:actions>
                <x-badge :tone="$year->status === 'open' ? 'success' : 'neutral'">{{ App\Models\FiscalYear::STATUSES[$year->status] }}</x-badge>
                @if ($canClose && $year->status === 'open')
                    <x-button size="sm" variant="secondary" icon="lock" data-action="post" data-url="{{ route('accounting.periods.close', $year) }}"
                        data-confirm="Close {{ $year->name }}?" data-confirm-message="The year's profit moves to Retained Earnings and every month is closed." data-confirm-text="Close Year" data-tone="primary"
                        :disabled="! $ended || $olderOpen" :data-disabled-reason="! $ended ? 'The year has not ended yet' : ($olderOpen ? 'Close the earlier year first' : null)">Close Year</x-button>
                @elseif ($canClose && $year->status === 'closed')
                    <x-button size="sm" variant="ghost" data-remote-modal="{{ route('accounting.periods.reason', ['year', $year->public_id]) }}" data-size="max-w-md"
                        :disabled="$newerClosed" :data-disabled-reason="$newerClosed ? 'Reopen the later year first' : null">Reopen</x-button>
                @endif
            </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Month</th><th>Journals</th><th>Status</th><th class="w-40"></th></tr></thead>
                    <tbody>
                        @foreach ($year->periods as $period)
                            <tr>
                                <td class="font-medium text-ink-900">{{ $period->name }}</td>
                                <td class="tabular-nums text-ink-700">{{ $counts[$period->id] ?? 0 }}</td>
                                <td><x-badge :tone="$tones[$period->status]">{{ App\Models\AccountingPeriod::STATUSES[$period->status] }}</x-badge>
                                    @if ($period->status !== 'open' && $period->locker)<span class="ml-2 text-[13px] text-ink-500">{{ $period->locker->name }} · {{ App\Support\Format::date($period->locked_at) }}</span>@endif
                                    @if ($period->status === 'open' && $period->status_reason)<span class="ml-2 text-[13px] text-ink-500">Unlocked: {{ $period->status_reason }}</span>@endif
                                </td>
                                <td class="text-right">
                                    @if ($canLock && $period->status === 'open')
                                        <x-button size="sm" variant="secondary" icon="lock" data-action="post" data-url="{{ route('accounting.periods.lock', $period) }}" data-confirm="Lock {{ $period->name }}?" data-confirm-message="Nothing can be dated in {{ $period->name }} while it is locked." data-confirm-text="Lock" data-tone="primary">Lock</x-button>
                                    @elseif ($canLock && $period->status === 'locked')
                                        <x-button size="sm" variant="ghost" data-remote-modal="{{ route('accounting.periods.reason', ['period', $period->public_id]) }}" data-size="max-w-md">Unlock</x-button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endforeach
</div>
