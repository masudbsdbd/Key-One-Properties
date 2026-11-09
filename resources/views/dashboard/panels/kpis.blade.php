<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($data['tiles'] as $t)
        <a href="{{ $t['href'] }}" class="summary-tile st-{{ $t['tone'] }} is-clickable items-start py-4">
            <span class="summary-tile-icon"><x-icon :name="$t['icon']" size="h-5 w-5" /></span>
            <span class="min-w-0">
                <span class="block text-[13px] font-medium text-ink-500">{{ $t['label'] }}</span>
                <span class="block truncate text-2xl font-semibold tabular-nums text-ink-900">{{ App\Support\Format::money($t['value'], false) }}</span>
                @if ($t['note'])<span @class(['block truncate text-[12px]', 'font-medium text-danger-600' => $t['alert'] ?? false, 'text-ink-500' => ! ($t['alert'] ?? false)])>{{ $t['note'] }}</span>@endif
            </span>
        </a>
    @endforeach
</div>
