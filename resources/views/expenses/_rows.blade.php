@forelse ($expenses as $expense)
    <tr>
        <td class="min-w-56"><a href="{{ route('expenses.show', $expense) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $expense->expense_no }}</a><p class="max-w-72 truncate text-[13px] text-ink-500">{{ App\Support\Format::date($expense->expense_date) }} · {{ $expense->description }}</p></td>
        <td class="text-ink-700">{{ $expense->category->label() }}</td>
        <td>@if ($expense->scope === 'project')<x-ref :to="$expense->project" class="text-ink-700">{{ $expense->project->code }}</x-ref>@else<x-badge tone="info">General</x-badge>@endif</td>
        <td class="text-ink-700">{{ $expense->payee_name ?: '—' }}</td>
        <td class="text-right font-medium"><x-money :value="$expense->amount" /></td>
        <td><x-status-badge :status="$expense->status" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="note" title="No expenses" /></td></tr>
@endforelse
@if ($expenses->total())
    <tr class="bg-surface-muted text-sm">
        <td colspan="6" class="px-5 py-3">
            <div class="flex flex-wrap justify-end gap-x-8 gap-y-1">
                <span class="text-ink-500">Pending <x-money :value="$totals->pending_total" class="ml-1 font-medium text-ink-900" /></span>
                <span class="text-ink-500">Approved <x-money :value="$totals->approved_total" class="ml-1 font-medium text-ink-900" /></span>
                <span class="text-ink-500">Paid <x-money :value="$totals->paid_total" class="ml-1 font-semibold text-ink-900" /></span>
            </div>
        </td>
    </tr>
@endif
