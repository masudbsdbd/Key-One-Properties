@if (! $data['rows'])
    <x-empty-state icon="check-square" title="No overdue customers" class="py-6" />
@else
    <ul class="divide-y divide-line">
        @foreach ($data['rows'] as $r)
            <li class="flex items-center gap-3 py-2.5">
                <span class="min-w-0 flex-1">
                    <x-ref :href="$r['href']" class="block truncate text-sm font-medium text-ink-900">{{ $r['name'] }}</x-ref>
                    <a href="tel:{{ $r['mobile'] }}" class="text-[12px] text-accent-700 hover:underline">{{ App\Support\Phone::display($r['mobile']) }}</a>
                </span>
                <span class="shrink-0 text-right">
                    <span class="block text-sm font-semibold tabular-nums text-danger-600">{{ App\Support\Format::money($r['amount']) }}</span>
                    <span class="block text-[12px] text-ink-500">{{ $r['days'] }} {{ Str::plural('day', $r['days']) }} late · {{ $r['installments'] }} {{ Str::plural('installment', $r['installments']) }}</span>
                </span>
            </li>
        @endforeach
    </ul>
    <p class="mt-3 flex items-center justify-between gap-3 text-[13px]"><a href="{{ $data['href'] }}" class="font-medium text-accent-700 hover:underline">All Overdue</a>@if ($data['more'])<span class="text-ink-500">{{ $data['more'] }} more</span>@endif</p>
@endif
