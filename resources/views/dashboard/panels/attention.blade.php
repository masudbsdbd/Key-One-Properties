@if (! $data['overdue'] && ! $data['items'])
    <x-empty-state icon="check" title="All clear" class="py-8" />
@else
    @if ($data['overdue'])
        <div class="mb-1 flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-ink-900">Overdue installments</p>
            <a href="{{ $data['overdue_href'] }}" class="text-[13px] font-medium text-accent-700 hover:underline">All dues</a>
        </div>
        <ul class="mb-4 divide-y divide-line">
            @foreach ($data['overdue'] as $row)
                @php $red = $row['days'] > $data['late_days']; @endphp
                <li class="flex items-center gap-3 py-2.5">
                    <span @class(['h-2 w-2 shrink-0 rounded-full', 'bg-danger-600' => $red, 'bg-warning-600' => ! $red]) aria-hidden="true"></span>
                    <span class="min-w-0 flex-1">
                        <x-ref :href="$row['href']" class="block truncate text-sm font-medium text-ink-900">{{ $row['name'] }}</x-ref>
                        <span class="block text-[12px] text-ink-500">{{ $row['mobile'] }}</span>
                    </span>
                    <span @class(['shrink-0 text-[12px] font-medium', 'text-danger-600' => $red, 'text-warning-600' => ! $red])>{{ $row['days'] }} {{ \Illuminate\Support\Str::plural('day', $row['days']) }} late</span>
                    <span class="w-28 shrink-0 text-right text-sm font-semibold tabular-nums text-ink-900"><x-money :value="$row['amount']" /></span>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($data['items'])
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($data['items'] as $i)
                <a href="{{ $i['href'] }}" class="flex items-center gap-3 rounded-control border border-line px-3 py-2.5 hover:border-accent-300 hover:bg-accent-50/40">
                    <span class="icon-tile icon-tile-sm tone-{{ $i['tone'] }}"><x-icon :name="$i['icon']" size="h-4 w-4" /></span>
                    <span class="min-w-0 flex-1 truncate text-sm text-ink-700">{{ $i['label'] }}</span>
                    <span class="shrink-0 text-right"><span class="block text-sm font-semibold tabular-nums text-ink-900">{{ number_format($i['count']) }}</span>@if ($i['amount'] !== null)<span class="block text-[12px] tabular-nums text-ink-500"><x-money :value="$i['amount']" /></span>@endif</span>
                </a>
            @endforeach
        </div>
    @endif
@endif
