@if (! $data['rows'])
    <x-empty-state icon="check-square" title="No late work" class="py-6" />
@else
    <ul class="divide-y divide-line">
        @foreach ($data['rows'] as $r)
            <li class="flex items-center gap-3 py-2.5">
                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-ink-900">{{ $r['name'] }}</span><x-ref :href="$r['href'] ? $r['href'].'#packages' : null" class="text-[12px] text-ink-500">{{ $r['code'] }}</x-ref></span>
                <span class="shrink-0 text-right"><span @class(['block text-[12px] font-medium', 'text-danger-600' => $r['days'] > App\Support\Dashboard::LATE_DAYS, 'text-warning-600' => $r['days'] <= App\Support\Dashboard::LATE_DAYS])>{{ $r['days'] }} {{ Str::plural('day', $r['days']) }} late</span><span class="block text-[12px] text-ink-500">{{ App\Support\Format::percent($r['progress']) }} done</span></span>
            </li>
        @endforeach
    </ul>
    @if ($data['total'] > count($data['rows']))<p class="mt-2 text-[12px] text-ink-500">{{ $data['total'] - count($data['rows']) }} more</p>@endif
@endif
