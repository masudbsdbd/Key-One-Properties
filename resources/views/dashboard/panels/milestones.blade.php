@if (! $data['rows'] && ! $data['soon'])
    <x-empty-state icon="check-square" title="On schedule" class="py-6" />
@else
    @if ($data['rows'])
        <p class="text-[12px] font-semibold uppercase tracking-wide text-danger-600">Late</p>
        <ul class="divide-y divide-line">
            @foreach ($data['rows'] as $m)
                <li class="flex items-center gap-3 py-2.5">
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-ink-900">{{ $m['title'] }}</span><x-ref :href="$m['href']" class="text-[12px] text-ink-500">{{ $m['code'] }}</x-ref></span>
                    <span @class(['shrink-0 text-[12px] font-medium', 'text-danger-600' => $m['days'] > App\Support\Dashboard::LATE_DAYS, 'text-warning-600' => $m['days'] <= App\Support\Dashboard::LATE_DAYS])>{{ $m['days'] }} {{ \Illuminate\Support\Str::plural('day', $m['days']) }} late</span>
                </li>
            @endforeach
        </ul>
        @if ($data['total'] > count($data['rows']))<p class="mt-2 text-[12px] text-ink-500">{{ $data['total'] - count($data['rows']) }} more</p>@endif
    @endif
    @if ($data['soon'])
        <p @class(['text-[12px] font-semibold uppercase tracking-wide text-warning-600', 'mt-4' => $data['rows']])>Next {{ $data['soon_days'] }} days</p>
        <ul class="divide-y divide-line">
            @foreach ($data['soon'] as $m)
                <li class="flex items-center gap-3 py-2.5">
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-ink-900">{{ $m['title'] }}</span><x-ref :href="$m['href']" class="text-[12px] text-ink-500">{{ $m['code'] }}</x-ref></span>
                    <span class="shrink-0 text-[12px] font-medium text-ink-600">{{ $m['days'] === 0 ? 'Today' : 'In '.$m['days'].' '.\Illuminate\Support\Str::plural('day', $m['days']) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
@endif
