@php
    $user = auth()->user();
    $canReverse = $user->can('materials.movement.reverse');
    $canReturn = fn ($issue) => App\Modules\Inventory\Policies\InventoryAccess::issueTo($user, $issue->warehouse, $issue->project);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.') ?: '0';
@endphp
@forelse ($issues as $issue)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('stock.movements', ['issue' => $issue->public_id]) }}" class="ref-link font-medium text-ink-900">{{ $issue->issue_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($issue->issued_at) }} · {{ $issue->creator?->name }}</p></td>
        <td class="text-ink-600">{{ $issue->warehouse->name }}</td>
        <td class="min-w-0 max-w-56">
            @foreach ($issue->lines->take(3) as $line)<span class="block truncate text-[13px] text-ink-700">{{ $line->material?->name }} <span class="tabular-nums text-ink-500">{{ $qty($line->quantity) }} {{ $line->material?->unit?->code }}</span>@if ((float) $line->returned_qty)<span class="tabular-nums text-success-600"> · {{ $qty($line->returned_qty) }} returned</span>@endif</span>@endforeach
            @if ($issue->lines->count() > 3)<span class="block text-[12px] text-ink-500">+{{ $issue->lines->count() - 3 }} more</span>@endif
            @if ($issue->purpose)<span class="block truncate text-[12px] text-ink-500">{{ $issue->purpose }}</span>@endif
        </td>
        <td class="text-ink-600">{{ $issue->workPackage?->code ?? '—' }}</td>
        @if ($showValue)<td class="text-right"><x-money :value="$issue->total_value ?? 0" /></td>@endif
        <td>@if ($issue->status === 'reversed')<x-badge tone="danger">Reversed</x-badge>@else<x-badge tone="success">Posted</x-badge>@endif</td>
        <td class="whitespace-nowrap text-right">
            @if ($issue->isReturnable() && $canReturn($issue))
                <x-button size="sm" variant="secondary" icon="restore" data-remote-modal="{{ route('stock.issues.return.form', $issue) }}" data-size="max-w-3xl">Return</x-button>
            @endif
            @if ($canReverse && $issue->status === 'posted' && $issue->lines->every(fn ($l) => ! (float) $l->returned_qty))
                <x-button size="sm" variant="ghost" data-remote-modal="{{ route('stock.issues.reverse.form', $issue) }}" data-size="max-w-md">Reverse</x-button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="{{ $showValue ? 7 : 6 }}"><x-empty-state icon="upload" title="No issues" /></td></tr>
@endforelse
