@php
    $user = auth()->user();
    $s = $expense->status;
    $canEdit = $expense->canBeCreatedBy($user);
    $tones = ['created' => 'info', 'updated' => 'info', 'submitted' => 'info', 'approved' => 'success', 'paid' => 'success', 'rejected' => 'danger', 'reversed' => 'danger'];
    $history = $expense->actions->map(fn ($a) => [
        'title' => Str::headline($a->action),
        'meta' => $a->actor->name.' · '.App\Support\Format::datetime($a->created_at),
        'body' => trim(implode("\n", array_filter([$a->reason, isset($a->metadata['method']) ? App\Models\Expense::METHODS[$a->metadata['method']].($a->metadata['reference'] ? ' · '.$a->metadata['reference'] : '') : null]))),
        'tone' => $tones[$a->action] ?? 'neutral',
    ])->all();
@endphp
<x-layouts.app :title="$expense->expense_no">
    <x-page-header :title="$expense->expense_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Expenses' => route('expenses.index'), $expense->expense_no => null]">
        <x-slot:actions>
            @if (in_array($s, App\Models\Expense::EDITABLE, true) && $canEdit)
                <x-button icon="arrow-right" data-action="post" data-url="{{ route('expenses.submit', $expense) }}" data-reload data-confirm="Submit {{ $expense->expense_no }} for approval?" data-confirm-text="Submit" data-tone="primary">Submit</x-button>
                <x-button variant="secondary" icon="edit" data-remote-modal="{{ route('expenses.edit', $expense) }}" data-size="max-w-3xl">Edit</x-button>
            @endif
            @if ($s === 'submitted' && $user->can('expenses.approve'))
                <x-button icon="check" data-action="post" data-url="{{ route('expenses.approve', $expense) }}" data-reload data-confirm="Approve {{ $expense->expense_no }}?" data-confirm-message="{{ App\Support\Format::money($expense->amount) }}{{ $expense->scope === 'project' ? ' will be added to '.$expense->project->name.' cost' : '' }}." data-confirm-text="Approve" data-tone="primary">Approve</x-button>
                <x-button variant="ghost" data-remote-modal="{{ route('expenses.reason', [$expense, 'reject']) }}" data-size="max-w-md">Reject</x-button>
            @endif
            @if ($s === 'approved' && $user->can('expenses.pay'))
                <x-button icon="tag" data-remote-modal="{{ route('expenses.pay.form', $expense) }}" data-size="max-w-lg">Pay</x-button>
            @endif
            @if (in_array($s, ['approved', 'paid'], true) && $user->can('expenses.reverse'))
                <x-button variant="ghost" icon="restore" data-remote-modal="{{ route('expenses.reason', [$expense, 'reverse']) }}" data-size="max-w-md">Reverse</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-lg font-semibold text-ink-900">{{ $expense->description }}</p>
                        <p class="mt-1 text-sm text-ink-500">{{ $expense->category->label() }} · {{ $expense->category->code }}</p>
                    </div>
                    <div class="text-right"><p class="text-2xl font-semibold text-ink-900"><x-money :value="$expense->amount" /></p><x-status-badge :status="$s" class="mt-1" /></div>
                </div>
                @if ($expense->status_reason && in_array($s, ['rejected', 'reversed'], true))
                    <div class="mt-4 rounded-card border border-danger-500/30 bg-danger-50 px-4 py-3 text-sm text-danger-600">{{ $expense->status_reason }}</div>
                @endif
                <dl class="mt-5 grid gap-5 border-t border-line pt-5 sm:grid-cols-4">
                    <div><dt class="detail-label">Scope</dt><dd class="detail-value">@if ($expense->scope === 'project')<a href="{{ route('projects.show', $expense->project) }}" class="text-accent-700 hover:underline">{{ $expense->project->name }}</a>@else General @endif</dd></div>
                    <div><dt class="detail-label">Work Package</dt><dd class="detail-value">{{ $expense->workPackage?->label() ?? '—' }}</dd></div>
                    <div><dt class="detail-label">Date</dt><dd class="detail-value">{{ App\Support\Format::date($expense->expense_date) }}</dd></div>
                    <div><dt class="detail-label">Paid To</dt><dd class="detail-value">{{ $expense->payee_name ?: '—' }}</dd></div>
                    @if ($s === 'paid' || $expense->paid_on)
                        <div><dt class="detail-label">Paid On</dt><dd class="detail-value">{{ App\Support\Format::date($expense->paid_on) }}</dd></div>
                        <div><dt class="detail-label">Method</dt><dd class="detail-value">{{ App\Models\Expense::METHODS[$expense->payment_method] }}</dd></div>
                        <div class="sm:col-span-2"><dt class="detail-label">Reference</dt><dd class="detail-value">{{ $expense->payment_reference ?: '—' }}</dd></div>
                    @endif
                </dl>
            </x-card>
            <x-attachments :owner="$expense" type="expense" title="Receipts" />
        </div>
        <x-card title="History" :padded="false"><x-timeline :items="$history" /></x-card>
    </div>
</x-layouts.app>
