@forelse ($expenses as $expense)
    <tr>
        <td class="min-w-56"><a href="{{ route('expenses.show', $expense) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $expense->expense_no }}</a><p class="max-w-72 truncate text-[13px] text-ink-500">{{ App\Support\Format::date($expense->expense_date) }} · {{ $expense->description }}</p></td>
        <td class="text-ink-700">{{ $expense->category->label() }}</td>
        @unless ($inProject ?? false)<td>@if ($expense->scope === 'project')<x-ref :to="$expense->project" class="text-ink-700">{{ $expense->project->code }}</x-ref>@else<x-badge tone="info">General</x-badge>@endif</td>@endunless
        <td class="text-ink-700">{{ $expense->payee_name ?: '—' }}</td>
        <td class="text-right font-medium"><x-money :value="$expense->amount" /></td>
        <td><x-status-badge :status="$expense->status" />@if (in_array($expense->status, ['rejected', 'reversed'], true) && $expense->status_reason)<span class="block max-w-40 truncate text-[12px] text-ink-500" data-tooltip="{{ $expense->status_reason }}">{{ $expense->status_reason }}</span>@endif</td>
    </tr>
@empty
    <tr><td colspan="{{ ($inProject ?? false) ? 5 : 6 }}"><x-empty-state icon="note" title="No expenses" /></td></tr>
@endforelse
