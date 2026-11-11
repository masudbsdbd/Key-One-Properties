@if (! $data['rows'])
    <x-empty-state icon="wallet" title="No accounts yet" class="py-6" />
@else
    <ul class="divide-y divide-line">
        @foreach ($data['rows'] as $r)
            <li class="flex items-center gap-3 py-2.5">
                <a href="{{ $r['href'] }}" class="min-w-0 flex-1 hover:text-accent-700"><span class="block truncate text-sm font-medium text-ink-900">{{ $r['name'] }}</span><span class="block truncate text-[12px] text-ink-500">{{ $r['kind'] }}</span></a>
                <span @class(['shrink-0 text-sm font-semibold tabular-nums', 'text-ink-900' => App\Support\Money::cmp($r['balance'], 0) >= 0, 'text-danger-600' => App\Support\Money::cmp($r['balance'], 0) < 0])><x-money :value="$r['balance']" :decimals="false" /></span>
            </li>
        @endforeach
    </ul>
    <p class="mt-3 flex items-center justify-between gap-3 border-t border-line pt-3 text-sm"><a href="{{ $data['href'] }}" class="font-medium text-accent-700 hover:underline">Cash & Bank</a><span class="font-semibold tabular-nums text-ink-900"><x-money :value="$data['total']" :decimals="false" /></span></p>
@endif
