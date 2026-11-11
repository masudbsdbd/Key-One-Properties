@if (! $data['rows'])
    <x-empty-state icon="layers" title="Stock is above reorder levels" class="py-6" />
@else
    <ul class="divide-y divide-line">
        @foreach ($data['rows'] as $r)
            <li class="flex items-center gap-3 py-2.5">
                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-ink-900">{{ $r['name'] }}</span><span class="block truncate text-[12px] text-ink-500">{{ $r['store'] }}</span></span>
                <span class="shrink-0 text-right"><span class="block text-sm font-semibold tabular-nums text-danger-600">{{ $r['qty'] }}</span><span class="block text-[12px] text-ink-500">Reorder at {{ $r['level'] }}</span></span>
                @if ($r['order'])<button type="button" class="btn btn-secondary btn-sm shrink-0" data-remote-modal="{{ $r['order'] }}" data-size="max-w-5xl">Order</button>@endif
            </li>
        @endforeach
    </ul>
    <p class="mt-3 flex items-center justify-between gap-3 text-[13px]"><a href="{{ $data['href'] }}" class="font-medium text-accent-700 hover:underline">All Low Stock</a>@if ($data['total'] > count($data['rows']))<span class="text-ink-500">{{ $data['total'] - count($data['rows']) }} more</span>@endif</p>
@endif
