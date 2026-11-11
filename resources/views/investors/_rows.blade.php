@forelse ($investors as $i)
    <tr>
        <td><a href="{{ route('investors.show', $i) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $i->name }}</a><p class="text-[13px] text-ink-500">{{ $i->investor_code }}</p></td>
        <td class="text-ink-700">{{ App\Support\Phone::display($i->mobile) ?: '—' }}<span class="block text-[13px] text-ink-500">{{ $i->email }}</span></td>
        <td class="text-right tabular-nums">{{ $i->active_agreements }}</td>
        <td class="text-right font-medium"><x-money :value="$i->capital ?? '0'" /></td>
        @if ($showPortal ?? false)
            <td><a href="{{ route('investors.show', $i) }}#portal" class="inline-flex">
                @if ($i->portal === 'active')<x-badge tone="success">Active</x-badge>@elseif ($i->portal === 'pending')<x-badge tone="warning">Pending Verification</x-badge>@else<span class="text-[13px] font-medium text-accent-700 hover:underline">Set Up</span>@endif
            </a></td>
        @endif
        <td><x-status-badge :status="$i->status" :labels="App\Models\Investor::STATUSES" /></td>
    </tr>
@empty
    <tr><td colspan="{{ ($showPortal ?? false) ? 6 : 5 }}"><x-empty-state icon="users" title="No investors" /></td></tr>
@endforelse
