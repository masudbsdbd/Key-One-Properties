@forelse ($categories as $category)
    <tr>
        <td class="font-medium text-ink-900">{{ $category->code }}</td>
        <td>@if ($category->parent)<span class="text-ink-400">{{ $category->parent->name }} ›</span> @endif{{ $category->name }}</td>
        <td class="text-ink-700">{{ App\Models\ExpenseCategory::SCOPES[$category->allowed_scope] }}</td>
        <td class="text-right tabular-nums">{{ $category->expenses_count }}</td>
        <td><x-status-badge :status="$category->is_active ? 'active' : 'inactive'" /></td>
        <td class="text-right"><button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('expense-categories.edit', $category) }}" data-size="max-w-xl" aria-label="Edit {{ $category->name }}"><x-icon name="edit" size="h-4 w-4" /></button></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="list" title="No categories" /></td></tr>
@endforelse
