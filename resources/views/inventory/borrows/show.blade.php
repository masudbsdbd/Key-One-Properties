@php
    $actor = auth()->user();
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.') ?: '0';
    $can = fn (string $a) => App\Modules\Inventory\Policies\InventoryAccess::borrow($actor, $borrow, $a);
    $history = $borrow->actions->map(function ($a) {
        $lines = collect($a->metadata['lines'] ?? [])->map(fn ($l) => $l[0].': '.rtrim(rtrim($l[1], '0'), '.'))->join(', ');

        return [
            'title' => Str::headline($a->action).(! empty($a->metadata['transfer_no']) ? ' · '.$a->metadata['transfer_no'] : ''),
            'meta' => $a->actor->name.' · '.App\Support\Format::datetime($a->created_at),
            'body' => trim(implode("\n", array_filter([$a->reason, $lines]))),
            'tone' => match ($a->action) { 'approved', 'issued' => 'success', 'rejected', 'cancelled' => 'danger', 'converted' => 'accent', default => 'info' },
        ];
    });
@endphp
<x-layouts.app :title="$borrow->borrow_no">
    <x-page-header :title="$borrow->borrow_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Borrows' => route('borrows.index'), $borrow->borrow_no => null]">
        <x-slot:actions>
            @if ($borrow->status === 'submitted' && $can('approve'))
                <x-button icon="check" data-remote-modal="{{ route('borrows.approve.form', $borrow) }}" data-size="max-w-2xl">Approve</x-button>
                <x-button variant="ghost" data-remote-modal="{{ route('borrows.reason', [$borrow, 'reject']) }}" data-size="max-w-md">Reject</x-button>
            @endif
            @if ($borrow->status === 'approved' && $can('issue'))
                <x-button icon="upload" data-action="post" data-url="{{ route('borrows.issue', $borrow) }}" data-reload data-confirm="Issue the approved materials?" data-confirm-message="Physical stock moves from {{ $borrow->sourceWarehouse->name }} to {{ $borrow->destinationWarehouse->name }}. Ownership and cost stay with {{ $borrow->sourceProject->name }}." data-tone="primary" data-confirm-text="Issue">Issue</x-button>
            @endif
            @if (in_array($borrow->status, App\Models\MaterialBorrow::OPEN, true))
                @if ($can('return'))<x-button variant="secondary" icon="restore" data-remote-modal="{{ route('borrows.settle.form', [$borrow, 'return']) }}" data-size="max-w-3xl">Return</x-button>@endif
                @if ($can('convert'))<x-button variant="secondary" icon="arrow-right" data-remote-modal="{{ route('borrows.settle.form', [$borrow, 'convert']) }}" data-size="max-w-3xl">Convert to Transfer</x-button>@endif
            @endif
            @if (in_array($borrow->status, ['submitted', 'approved'], true) && $can('cancel'))
                <x-button variant="ghost" data-remote-modal="{{ route('borrows.reason', [$borrow, 'cancel']) }}" data-size="max-w-md">Cancel</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card>
                <div class="grid gap-5 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                    <div><p class="detail-label">From</p><p class="detail-value font-semibold">{{ $borrow->sourceProject->name }}</p><p class="text-[13px] text-ink-500">{{ $borrow->sourceWarehouse->name }} · owner</p></div>
                    <span class="icon-tile icon-tile-md tone-accent mx-auto"><x-icon name="arrow-right" /></span>
                    <div><p class="detail-label">To</p><p class="detail-value font-semibold">{{ $borrow->destinationProject->name }}</p><p class="text-[13px] text-ink-500">{{ $borrow->destinationWarehouse->name }} · custody</p></div>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-line pt-4 text-[13px] text-ink-500">
                    <x-status-badge :status="$borrow->status" /><span>Requested by {{ $borrow->requester->name }}</span><span>{{ App\Support\Format::datetime($borrow->created_at) }}</span>
                </div>
                @if ($borrow->notes)<p class="mt-3 text-sm text-ink-700">{{ $borrow->notes }}</p>@endif
            </x-card>

            <x-card title="Materials" :padded="false">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Material</th><th class="text-right">Requested</th><th class="text-right">Approved</th><th class="text-right">Issued</th><th class="text-right">Returned</th><th class="text-right">Converted</th><th class="text-right">Outstanding</th>@can('materials.valuation.view')<th class="text-right">Locked Cost</th>@endcan</tr></thead>
                        <tbody>
                            @foreach ($borrow->lines as $line)
                                <tr>
                                    <td class="min-w-48"><p class="font-medium text-ink-900">{{ $line->material->name }}</p><p class="text-[13px] text-ink-500">{{ $line->material->sku }} · {{ $line->material->unit->code }}</p></td>
                                    <td class="text-right tabular-nums">{{ $q($line->requested_qty) }}</td>
                                    <td class="text-right tabular-nums">{{ $line->approved_qty === null ? '—' : $q($line->approved_qty) }}</td>
                                    <td class="text-right tabular-nums">{{ $q($line->issued_qty) }}</td>
                                    <td class="text-right tabular-nums">{{ $q($line->returned_qty) }}</td>
                                    <td class="text-right tabular-nums">{{ $q($line->converted_qty) }}</td>
                                    <td class="text-right font-semibold tabular-nums {{ App\Support\Money::isPositive($line->outstanding()) ? 'text-warning-600' : '' }}">{{ $q($line->outstanding()) }}</td>
                                    @can('materials.valuation.view')<td class="text-right">{{ $line->locked_unit_cost === null ? '—' : App\Support\Format::money($line->locked_unit_cost) }}</td>@endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($transfers->isNotEmpty())
                <x-card title="Conversions" :padded="false">
                    <ul class="divide-y divide-line">
                        @foreach ($transfers as $transfer)
                            <li class="flex items-center justify-between px-5 py-3">
                                <a href="{{ route('transfers.show', $transfer) }}" class="font-medium text-accent-700 hover:underline">{{ $transfer->transfer_no }}</a>
                                <span class="text-sm text-ink-600">{{ App\Support\Format::datetime($transfer->posted_at) }}</span>
                                @can('materials.valuation.view')<x-money :value="App\Support\Money::add(...$transfer->lines->pluck('total_value')->map(fn ($v) => (string) $v)->all())" />@endcan
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <x-card title="History" :padded="false">
            <x-timeline :items="$history" />
        </x-card>
    </div>
</x-layouts.app>
