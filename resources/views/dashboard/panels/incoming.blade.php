@if (! $data['rows'])
    <x-empty-state icon="calendar" title="Nothing due this week" class="py-6" />
@else
    <p class="flex items-baseline justify-between gap-3"><span class="text-[13px] text-ink-500">{{ $data['count'] }} {{ Str::plural('installment', $data['count']) }}</span><span class="text-lg font-semibold tabular-nums text-success-600">{{ App\Support\Format::money($data['total']) }}</span></p>
    <ul class="mt-2 divide-y divide-line">
        @foreach ($data['rows'] as $r)
            <li class="flex items-center gap-3 py-2.5">
                <span class="min-w-0 flex-1"><x-ref :href="$r['href']" class="block truncate text-sm font-medium text-ink-900">{{ $r['name'] }}</x-ref><span class="block truncate text-[12px] text-ink-500">{{ $r['unit'] }} · <a href="tel:{{ $r['mobile'] }}" class="text-accent-700 hover:underline">{{ App\Support\Phone::display($r['mobile']) }}</a></span></span>
                <span class="shrink-0 text-right"><span class="block text-sm font-semibold tabular-nums text-ink-900">{{ App\Support\Format::money($r['amount']) }}</span><span class="block text-[12px] font-medium text-ink-500">{{ $r['days'] === 0 ? 'Due today' : 'Due '.$r['due'] }}</span></span>
            </li>
        @endforeach
    </ul>
    <p class="mt-3 flex items-center justify-between gap-3 text-[13px]"><a href="{{ $data['href'] }}" class="font-medium text-accent-700 hover:underline">All Due This Week</a>@if ($data['more'])<span class="text-ink-500">{{ $data['more'] }} more</span>@endif</p>
@endif
