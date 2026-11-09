@php($actor = auth()->user())
@php($q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.') ?: '0')
@php($can = fn (string $a) => App\Modules\Inventory\Policies\InventoryAccess::transfer($actor, $transfer, $a))
@php($showValue = $actor->can('materials.valuation.view'))
<x-layouts.app :title="$transfer->transfer_no">
    <x-page-header :title="$transfer->transfer_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Transfers' => route('transfers.index'), $transfer->transfer_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('transfers.document', $transfer)" />
            @if ($transfer->status === 'submitted' && $can('approve'))
                <x-button icon="check" data-action="post" data-url="{{ route('transfers.approve', $transfer) }}" data-reload data-confirm="Approve {{ $transfer->transfer_no }}?" data-tone="primary" data-confirm-text="Approve">Approve</x-button>
                <x-button variant="ghost" data-remote-modal="{{ route('transfers.reason', [$transfer, 'reject']) }}" data-size="max-w-md">Reject</x-button>
            @endif
            @if ($transfer->status === 'approved' && $can('post'))
                <x-button icon="upload" data-action="post" data-url="{{ route('transfers.post', $transfer) }}" data-reload data-confirm="Post {{ $transfer->transfer_no }}?" data-confirm-message="Stock, ownership and inventory value move from {{ $transfer->sourceWarehouse->name }} to {{ $transfer->destinationWarehouse->name }}." data-tone="primary" data-confirm-text="Post">Post</x-button>
            @endif
            @if (in_array($transfer->status, ['submitted', 'approved'], true) && $can('cancel'))
                <x-button variant="ghost" data-remote-modal="{{ route('transfers.reason', [$transfer, 'cancel']) }}" data-size="max-w-md">Cancel</x-button>
            @endif
            @if ($transfer->status === 'posted' && $transfer->kind === 'direct' && $can('reverse'))
                <x-button variant="danger-soft" data-remote-modal="{{ route('transfers.reason', [$transfer, 'reverse']) }}" data-size="max-w-md">Reverse</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-card class="min-w-0 xl:col-span-2">
            <div class="grid gap-5 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                <div><p class="detail-label">From</p><p class="detail-value font-semibold">{{ $transfer->sourceWarehouse->name }}</p><x-ref :to="$transfer->sourceWarehouse->project" class="block text-[13px] text-ink-500">{{ $transfer->sourceWarehouse->project?->name ?? 'Company' }}</x-ref></div>
                <span class="icon-tile icon-tile-md tone-accent mx-auto"><x-icon name="arrow-right" /></span>
                <div><p class="detail-label">To</p><p class="detail-value font-semibold">{{ $transfer->destinationWarehouse->name }}</p><x-ref :to="$transfer->destinationWarehouse->project" class="block text-[13px] text-ink-500">{{ $transfer->destinationWarehouse->project?->name ?? 'Company' }}</x-ref></div>
            </div>
            <div class="mt-6 overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Material</th><th class="text-right">Quantity</th>@if ($showValue)<th class="text-right">Unit Cost</th><th class="text-right">Value</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($transfer->lines as $line)
                            <tr>
                                <td>{{ $line->material->name }} <span class="text-ink-500">({{ $line->material->unit->code }})</span></td>
                                <td class="text-right tabular-nums">{{ $q($line->quantity) }}</td>
                                @if ($showValue)<td class="text-right">{{ $line->unit_cost === null ? '—' : App\Support\Format::money($line->unit_cost) }}</td><td class="text-right">{{ $line->total_value === null ? '—' : App\Support\Format::money($line->total_value) }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
        <x-card title="Details">
            <dl class="space-y-4 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-ink-500">Status</dt><dd><x-status-badge :status="$transfer->status" /></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-500">Type</dt><dd>{{ App\Models\MaterialTransfer::KINDS[$transfer->kind] }}</dd></div>
                @if ($transfer->borrow)<div class="flex justify-between gap-4"><dt class="text-ink-500">Borrow</dt><dd><a class="text-accent-700 hover:underline" href="{{ route('borrows.show', $transfer->borrow) }}">{{ $transfer->borrow->borrow_no }}</a></dd></div>@endif
                <div class="flex justify-between gap-4"><dt class="text-ink-500">Requested by</dt><dd>{{ $transfer->requester->name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-500">Requested</dt><dd>{{ App\Support\Format::datetime($transfer->created_at) }}</dd></div>
                @if ($transfer->approved_at)<div class="flex justify-between gap-4"><dt class="text-ink-500">Approved</dt><dd>{{ App\Support\Format::datetime($transfer->approved_at) }}</dd></div>@endif
                @if ($transfer->posted_at)<div class="flex justify-between gap-4"><dt class="text-ink-500">Posted</dt><dd>{{ App\Support\Format::datetime($transfer->posted_at) }}</dd></div>@endif
                @if ($transfer->reason)<div><dt class="text-ink-500">Reason</dt><dd class="mt-1">{{ $transfer->reason }}</dd></div>@endif
                @if ($transfer->rejection_reason)<div><dt class="text-ink-500">Rejection</dt><dd class="mt-1 text-danger-600">{{ $transfer->rejection_reason }}</dd></div>@endif
                @if ($transfer->reversal_reason)<div><dt class="text-ink-500">Reversal</dt><dd class="mt-1 text-danger-600">{{ $transfer->reversal_reason }}</dd></div>@endif
            </dl>
        </x-card>
    </div>
</x-layouts.app>
