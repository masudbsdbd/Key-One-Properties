@php($actor = auth()->user())
@php($types = App\Models\LabourType::names())
@forelse ($entries as $entry)
    @php($canApprove = $actor->can('construction:labour-approve', $entry->project))
    <tr>
        @if ($selectable ?? false)<td>@if ($canApprove && $entry->status === 'submitted')<input type="checkbox" class="form-check" value="{{ $entry->public_id }}" data-select-row aria-label="Select {{ $entry->entry_no }}">@endif</td>@endif
        <td class="whitespace-nowrap"><p class="font-medium text-ink-900">{{ $entry->entry_no }}</p><p class="text-[13px] text-ink-500">{{ $entry->creator?->name }}</p></td>
        @if ($showProject)<td class="whitespace-nowrap text-ink-600"><x-ref :to="$entry->project">{{ $entry->project->name }}</x-ref></td>@endif
        <td class="whitespace-nowrap">{{ App\Support\Format::date($entry->work_date) }}</td>
        <td class="whitespace-nowrap">{{ $types[$entry->labour_type] ?? $entry->labour_type }}<span class="block text-[13px] text-ink-500">{{ $entry->headcount }} × {{ rtrim(rtrim($entry->units, '0'), '.') }} @ {{ App\Support\Format::money($entry->rate, false) }}</span></td>
        <td class="text-ink-600">{{ $entry->workPackage?->code ?? '—' }}</td>
        <td class="text-right"><x-money :value="$entry->total_amount" /></td>
        <td><x-status-badge :status="$entry->status" />@if ($entry->rejection_reason || $entry->reversal_reason)<span class="block max-w-32 truncate text-[13px] text-ink-500" data-tooltip="{{ $entry->rejection_reason ?? $entry->reversal_reason }}">{{ $entry->rejection_reason ?? $entry->reversal_reason }}</span>@endif</td>
        <td class="whitespace-nowrap text-right">
            @if ($canApprove && $entry->status === 'submitted')
                <button type="button" class="btn btn-ghost btn-icon text-success-600" data-row-action="post" data-url="{{ route('labour.approve', $entry) }}" data-confirm="Approve {{ $entry->entry_no }}?" data-confirm-message="Adds {{ App\Support\Format::money($entry->total_amount) }} labour cost to {{ $entry->project->name }}." data-tone="primary" data-confirm-text="Approve" aria-label="Approve {{ $entry->entry_no }}"><x-icon name="check" size="h-4 w-4" /></button>
                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-remote-modal="{{ route('labour.reason', [$entry, 'reject']) }}" data-size="max-w-md" aria-label="Reject {{ $entry->entry_no }}"><x-icon name="x" size="h-4 w-4" /></button>
            @elseif ($canApprove && $entry->status === 'approved')
                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('labour.reason', [$entry, 'reverse']) }}" data-size="max-w-md" aria-label="Reverse {{ $entry->entry_no }}"><x-icon name="restore" size="h-4 w-4" /></button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="{{ ($showProject ? 8 : 7) + (($selectable ?? false) ? 1 : 0) }}"><x-empty-state icon="users" title="No labour entries" /></td></tr>
@endforelse
