@if (! $data['rows'])
    <x-empty-state icon="check-square" title="Nothing to pay" class="py-6" />
@else
    <p class="flex items-baseline justify-between gap-3"><span class="text-[13px] text-ink-500">{{ $data['count'] }} {{ Str::plural('payment', $data['count']) }}</span><span class="text-lg font-semibold tabular-nums text-ink-900">{{ App\Support\Format::money($data['total']) }}</span></p>
    <ul class="mt-2 divide-y divide-line">
        @foreach ($data['rows'] as $r)
            <li class="flex items-center gap-3 py-2.5">
                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-ink-900">{{ $r['who'] ?: '—' }}</span><x-ref :href="$r['href']" class="text-[12px] text-ink-500">{{ $r['what'] }}</x-ref></span>
                <span class="shrink-0 text-right">
                    <span class="block text-sm font-semibold tabular-nums text-ink-900">{{ App\Support\Format::money($r['amount']) }}</span>
                    <span @class(['block text-[12px] font-medium', 'text-danger-600' => $r['days'] !== null && $r['days'] < 0, 'text-warning-600' => $r['days'] !== null && $r['days'] >= 0, 'text-ink-500' => $r['days'] === null])>
                        @if ($r['days'] === null) Payable now @elseif ($r['days'] < 0) {{ abs($r['days']) }} {{ Str::plural('day', abs($r['days'])) }} overdue @elseif ($r['days'] === 0) Due today @else Due {{ $r['due'] }} @endif
                    </span>
                </span>
            </li>
        @endforeach
    </ul>
    @if ($data['more'])<p class="mt-2 text-[12px] text-ink-500">{{ $data['more'] }} more</p>@endif
@endif
