<div class="rounded-card border {{ $conflicts ? 'border-danger-500/40' : 'border-line' }}">
    <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <p class="text-sm font-semibold text-ink-900">{{ $plan->count() }} units · {{ $building->name }}</p>
        @if ($conflicts)
            <x-badge tone="danger">{{ $conflicts }} conflicts</x-badge>
        @else
            <x-badge tone="success">Ready</x-badge>
        @endif
    </div>
    <div class="max-h-72 overflow-y-auto p-4">
        @foreach ($plan->groupBy(fn ($r) => $r['floor']->id) as $rows)
            <div class="mb-3 flex flex-wrap items-center gap-2 last:mb-0">
                <span class="w-28 shrink-0 text-[13px] text-ink-500">{{ $rows->first()['floor']->label }}</span>
                @foreach ($rows as $row)
                    <span @class(['badge', 'badge-danger' => $row['exists'] || $row['duplicate'], 'badge-accent' => ! $row['exists'] && ! $row['duplicate']]) @if ($row['exists']) data-tooltip="Already exists" @elseif ($row['duplicate']) data-tooltip="Repeated" @endif>{{ $row['flat_no'] }}</span>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
<input type="hidden" data-bulk-ready value="{{ $conflicts ? '0' : '1' }}">
