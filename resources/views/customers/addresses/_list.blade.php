@php($canEdit = auth()->user()->can('update', $customer))
@if ($customer->addresses->isEmpty())
    <x-empty-state icon="map-pin" title="No addresses" />
@else
    <ul class="divide-y divide-line">
        @foreach ($customer->addresses as $address)
            <li class="flex items-start gap-4 px-5 py-4">
                <span class="icon-tile icon-tile-sm tone-accent"><x-icon name="map-pin" size="h-4 w-4" /></span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-medium text-ink-900">{{ App\Models\CustomerAddress::TYPES[$address->address_type] ?? $address->address_type }}</p>
                        @if ($address->is_primary)<x-badge tone="warning">Primary</x-badge>@endif
                    </div>
                    <p class="mt-0.5 text-sm text-ink-600">{{ $address->oneLine() }}</p>
                </div>
                @if ($canEdit)
                    <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('customers.addresses.edit', [$customer, $address]) }}" aria-label="Edit address"><x-icon name="edit" size="h-4 w-4" /></button>
                    <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('customers.addresses.destroy', [$customer, $address]) }}" data-confirm="Remove this address?" data-confirm-text="Remove" aria-label="Remove address"><x-icon name="trash" size="h-4 w-4" /></button>
                @endif
            </li>
        @endforeach
    </ul>
@endif
