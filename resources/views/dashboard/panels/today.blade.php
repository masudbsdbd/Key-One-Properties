@php $money = fn ($v) => App\Support\Format::money($v, false); @endphp
<table class="w-full text-sm">
    <thead><tr class="text-[12px] text-ink-500"><th class="pb-2 text-left font-medium"></th><th class="pb-2 text-right font-medium">Money In</th><th class="pb-2 text-right font-medium">Money Out</th><th class="pb-2 text-right font-medium">Net</th></tr></thead>
    <tbody class="divide-y divide-line">
        @foreach ($data['periods'] as $p)
            <tr @class(['font-semibold' => $loop->first])>
                <td class="py-2.5 text-ink-700">{{ $p['label'] }}</td>
                <td class="py-2.5 text-right tabular-nums text-success-600">{{ $money($p['in']) }}</td>
                <td class="py-2.5 text-right tabular-nums text-warning-600">{{ $money($p['out']) }}</td>
                <td @class(['py-2.5 text-right tabular-nums', 'text-danger-600' => (float) $p['net'] < 0, 'text-ink-900' => (float) $p['net'] >= 0])>{{ $money($p['net']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@if ($data['in'] || $data['out'])
    @foreach (['in' => 'Received Today', 'out' => 'Paid Today'] as $side => $heading)
        @if ($data[$side])
            <p class="mt-4 text-[12px] font-semibold uppercase tracking-wide text-ink-500">{{ $heading }}</p>
            <ul class="divide-y divide-line">
                @foreach ($data[$side] as $line)
                    <li><a href="{{ $line['href'] }}" class="flex items-center justify-between gap-3 py-2 text-sm hover:text-accent-700"><span class="text-ink-700">{{ $line['label'] }} <span class="text-[12px] text-ink-500">· {{ $line['count'] }}</span></span><span class="font-medium tabular-nums text-ink-900">{{ App\Support\Format::money($line['amount']) }}</span></a></li>
                @endforeach
            </ul>
        @endif
    @endforeach
@endif
@if (Route::has('reports.show') && auth()->user()->can('reports.view'))<a href="{{ route('reports.show', 'daily-cash-book') }}" class="mt-4 inline-block text-[13px] font-medium text-accent-700 hover:underline">Daily Cash Book</a>@endif
