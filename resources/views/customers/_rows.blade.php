@forelse ($customers as $customer)
    <tr>
        <td>
            <a href="{{ $customer->trashed() ? '#' : route('customers.show', $customer) }}" class="flex items-center gap-3 {{ $customer->trashed() ? 'pointer-events-none' : '' }}">
                <x-avatar :name="$customer->full_name" />
                <span class="min-w-0">
                    <span class="block truncate font-medium text-ink-900">{{ $customer->full_name }}</span>
                    <span class="block text-[13px] text-ink-500">{{ $customer->customer_code }}</span>
                </span>
            </a>
        </td>
        <td class="whitespace-nowrap"><span class="inline-flex items-center gap-2"><x-icon name="phone" size="h-4 w-4 text-ink-400" />{{ App\Support\Phone::display($customer->primary_mobile) }}</span></td>
        <td class="text-ink-600">{{ $customer->email ?: '—' }}</td>
        <td>@if ($customer->source)<x-badge :tone="App\Models\Customer::SOURCE_TONES[$customer->source] ?? 'neutral'">{{ App\Models\Customer::SOURCES[$customer->source] }}</x-badge>@else — @endif</td>
        <td><x-badge :tone="$customer->status === 'active' ? 'success' : 'neutral'">{{ App\Models\Customer::STATUSES[$customer->status] ?? $customer->status }}</x-badge></td>
        <td class="whitespace-nowrap text-ink-600">
            @if ($customer->trashed())
                <button type="button" class="btn btn-sm btn-secondary" data-row-action="post" data-url="{{ route('customers.restore', $customer->public_id) }}" data-confirm="Restore {{ $customer->full_name }}?" data-tone="primary" data-confirm-text="Restore"><x-icon name="restore" size="h-4 w-4" />Restore</button>
            @else
                {{ App\Support\Format::date($customer->created_at?->timezone(config('app.display_timezone'))) }}<span class="block text-[13px] text-ink-400">{{ $customer->creator?->name }}</span>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="users" title="No customers found" /></td></tr>
@endforelse
