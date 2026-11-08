@php($total = App\Support\Money::add('0', ...array_values($aging)))
<div class="space-y-3">
    @foreach (['current' => 'Not yet due', '1-30' => '1–30 days late', '31-60' => '31–60 days late', '61-90' => '61–90 days late', '90+' => 'Over 90 days late'] as $key => $label)
        @php($pct = App\Support\Money::isPositive($total) ? round((float) $aging[$key] / (float) $total * 100) : 0)
        <div>
            <div class="flex justify-between text-sm"><span class="text-ink-700">{{ $label }}</span><x-money :value="$aging[$key]" @class(['font-medium', 'text-danger-600' => $key === '90+' && App\Support\Money::isPositive($aging[$key])]) /></div>
            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full {{ $key === 'current' ? 'bg-success-600' : 'bg-warning-600' }}" style="width: {{ $pct }}%"></div></div>
        </div>
    @endforeach
</div>
