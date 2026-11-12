@forelse ($events as $event)
    <tr>
        <td class="whitespace-nowrap text-ink-700">{{ App\Support\Format::date($event->effective_at) }}</td>
        <td><p class="font-medium text-ink-900">{{ $labels[$event->event_type] ?? $event->event_type }}</p>
            <p class="text-[13px] text-ink-500">@if ($event->source)<x-ref :to="$event->source">{{ App\Support\ActivityPresenter::type($event->source_type) }}</x-ref>@endif @if ($event->project) · <x-ref :to="$event->project">{{ $event->project->code }}</x-ref>@endif</p></td>
        <td class="text-right tabular-nums">@if ($event->amount !== null)<x-money :value="$event->amount" />@endif</td>
        <td class="max-w-md">@if ($event->processing_status === 'failed')<p class="text-sm text-danger-600">{{ $event->last_error }}</p>@else<x-badge tone="warning">Waiting</x-badge>@endif</td>
        <td class="text-right">
            @if ($canRun && App\Modules\Accounting\Controllers\ReconciliationController::needsAccount($event))
                <x-button size="sm" data-remote-modal="{{ route('accounting.reconciliation.account', $event->event_uuid) }}" data-size="max-w-md">Choose Account</x-button>
            @elseif ($canRun)
                <x-button size="sm" variant="secondary" data-row-action="post" data-url="{{ route('accounting.reconciliation.retry', $event->event_uuid) }}">Post</x-button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="5"><x-empty-state icon="check-square" title="Nothing is waiting" /></td></tr>
@endforelse
