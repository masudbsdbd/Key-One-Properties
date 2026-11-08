<form method="POST" action="{{ route('borrows.approve', $borrow) }}" data-ajax-form data-reload-on-success class="space-y-5">
    @csrf
    <table class="data-table">
        <thead><tr><th>Material</th><th class="text-right">Requested</th><th class="w-40">Approve</th></tr></thead>
        <tbody>
            @foreach (App\Support\LineKeys::ordered($borrow->lines) as $line)
                <tr>
                    <td>{{ $line->material->name }} <span class="text-ink-500">({{ $line->material->unit->code }})</span></td>
                    <td class="text-right tabular-nums">{{ rtrim(rtrim($line->requested_qty, '0'), '.') }}</td>
                    <td data-field><input name="approved[{{ $loop->index }}]" value="{{ rtrim(rtrim($line->requested_qty, '0'), '.') }}" class="form-control h-9" inputmode="decimal" aria-label="Approved quantity for {{ $line->material->name }}"><p class="form-error" data-error hidden></p></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Approve</x-button>
    </x-form.actions>
</form>
