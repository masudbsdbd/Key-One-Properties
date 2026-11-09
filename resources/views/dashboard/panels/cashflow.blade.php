<div class="mb-4 flex flex-wrap items-center gap-4 text-[13px] text-ink-500">
    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-success-600"></span>Money in</span>
    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-warning-600"></span>Money out</span>
    <span class="ml-auto">Company · last 6 months</span>
</div>
<ul class="space-y-3" role="list">
    @foreach ($data['rows'] as $r)
        <li class="grid grid-cols-[4.5rem_1fr_7.5rem] items-center gap-3">
            <span class="text-[13px] text-ink-500">{{ $r['month'] }}</span>
            <span class="space-y-1" aria-hidden="true">
                <span class="block h-2 rounded-full bg-success-600" style="width: {{ max(0.5, (float) $r['in'] / $data['max'] * 100) }}%"></span>
                <span class="block h-2 rounded-full bg-warning-600" style="width: {{ max(0.5, (float) $r['out'] / $data['max'] * 100) }}%"></span>
            </span>
            <span class="text-right text-[12px] leading-tight tabular-nums">
                <span class="block text-success-600"><x-money :value="$r['in']" /></span>
                <span class="block text-warning-600"><x-money :value="$r['out']" /></span>
            </span>
        </li>
    @endforeach
</ul>
