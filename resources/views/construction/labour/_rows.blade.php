@php($actor = auth()->user())
@php($types = App\Models\LabourType::names())
@forelse ($entries as $entry)
    @php($canApprove = $actor->can('construction:labour-approve', $entry->project))
    <tr>
        <td class="whitespace-nowrap"><p class="font-medium text-ink-900">{{ $entry->entry_no }}</p><p class="text-[13px] text-ink-500">{{ $entry->creator?->name }}</p></td>
        @if ($showProject)<td class="whitespace-nowrap text-ink-600"><x-ref :to="$entry->project">{{ $entry->project->name }}</x-ref></td>@endif
        <td class="whitespace-nowrap">{{ App\Support\Format::date($entry->work_date) }}</td>
        <td class="whitespace-nowrap">{{ $types[$entry->labour_type] ?? $entry->labour_type }}<span class="block text-[13px] text-ink-500">{{ $entry->headcount }} × {{ rtrim(rtrim($entry->units, '0'), '.') }} @ {{ App\Support\Format::money($entry->rate, false) }}</span></td>
        <td class="text-ink-600">{{ $entry->workPackage?->code ?? '—' }}</td>
        <td class="text-right"><x-money :value="$entry->total_amount" /></td>
        <td><x-status-badge :status="$entry->status" />@if ($entry->rejection_reason || $entry->reversal_reason)<span class="block max-w-48 truncate text-[13px] text-ink-500" title="{{ $entry->rejection_reason ?? $entry->reversal_reason }}">{{ $entry->rejection_reason ?? $entry->reversal_reason }}</span>@endif</td>
        <td class="whitespace-nowrap text-right">
            @if ($canApprove && $entry->status === 'submitted')
                <x-button size="sm" data-row-action="post" data-url="{{ route('labour.approve', $entry) }}" data-confirm="Approve {{ $entry->entry_no }}?" data-confirm-message="Adds {{ App\Support\Format::money($entry->total_amount) }} labour cost to {{ $entry->project->name }}." data-tone="primary" data-confirm-text="Approve">Approve</x-button>
                <x-button size="sm" variant="ghost" data-remote-modal="{{ route('labour.reason', [$entry, 'reject']) }}" data-size="max-w-md">Reject</x-button>
            @elseif ($canApprove && $entry->status === 'approved')
                <x-button size="sm" variant="ghost" data-remote-modal="{{ route('labour.reason', [$entry, 'reverse']) }}" data-size="max-w-md">Reverse</x-button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="{{ $showProject ? 8 : 7 }}"><x-empty-state icon="users" title="No labour entries" /></td></tr>
@endforelse
