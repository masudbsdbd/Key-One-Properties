@php $money = fn ($v) => App\Support\Format::money($v); @endphp
<div class="grid grid-cols-3 gap-3 text-center">
    <div class="rounded-card bg-success-50 px-2 py-3"><p class="text-[12px] text-ink-600">Money In</p><p class="mt-0.5 text-base font-semibold tabular-nums text-success-600">{{ $money($data['total_in']) }}</p></div>
    <div class="rounded-card bg-warning-50 px-2 py-3"><p class="text-[12px] text-ink-600">Money Out</p><p class="mt-0.5 text-base font-semibold tabular-nums text-warning-600">{{ $money($data['total_out']) }}</p></div>
    <div class="rounded-card bg-surface-muted px-2 py-3"><p class="text-[12px] text-ink-600">Net</p><p @class(['mt-0.5 text-base font-semibold tabular-nums', 'text-danger-600' => (float) $data['net'] < 0, 'text-ink-900' => (float) $data['net'] >= 0])>{{ $money($data['net']) }}</p></div>
</div>
@if (! $data['in'] && ! $data['out'])
    <x-empty-state icon="tag" title="No money moved today" class="py-6" />
@else
    @foreach (['in' => 'Received', 'out' => 'Paid'] as $side => $heading)
        @if ($data[$side])
            <p class="mt-4 text-[12px] font-semibold uppercase tracking-wide text-ink-500">{{ $heading }}</p>
            <ul class="divide-y divide-line">
                @foreach ($data[$side] as $line)
                    <li><a href="{{ $line['href'] }}" class="flex items-center justify-between gap-3 py-2 text-sm hover:text-accent-700"><span class="text-ink-700">{{ $line['label'] }} <span class="text-[12px] text-ink-500">· {{ $line['count'] }}</span></span><span class="font-medium tabular-nums text-ink-900">{{ $money($line['amount']) }}</span></a></li>
                @endforeach
            </ul>
        @endif
    @endforeach
@endif
