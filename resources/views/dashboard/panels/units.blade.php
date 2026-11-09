@if (! $data['rows'])
    <x-empty-state icon="door" title="No units" class="py-6" />
@else
    <ul class="divide-y divide-line">
        @foreach ($data['rows'] as $r)
            <li class="py-2.5">
                <div class="flex items-baseline justify-between gap-3 text-sm"><x-ref :href="$r['href']" class="truncate font-medium text-ink-900">{{ $r['code'] }}</x-ref><span class="text-[12px] text-ink-500">{{ $r['total'] }} units</span></div>
                <div class="mt-1.5 flex h-2 overflow-hidden rounded-full bg-ink-900/[0.06]" aria-hidden="true">
                    <span class="bg-success-600" style="width: {{ $r['total'] ? $r['sold'] / $r['total'] * 100 : 0 }}%"></span>
                    <span class="bg-warning-600" style="width: {{ $r['total'] ? $r['booked'] / $r['total'] * 100 : 0 }}%"></span>
                </div>
                <p class="mt-1 text-[12px] text-ink-500">{{ $r['sold'] }} sold · {{ $r['booked'] }} booked · @if ($r['not_released']){{ $r['not_released'] }} not released · @endif<a href="{{ $r['available_href'] }}" class="text-accent-700 hover:underline">{{ $r['available'] }} available</a> (<x-money :value="$r['available_value']" />)</p>
            </li>
        @endforeach
    </ul>
@endif
