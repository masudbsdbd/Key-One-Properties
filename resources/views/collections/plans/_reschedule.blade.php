<form method="POST" action="{{ route('installment-plans.reschedule', $plan) }}" data-ajax-form data-reload-on-success class="space-y-5">
    @csrf
    <div class="rounded-card border border-line">
        <table class="data-table">
            <thead><tr><th>Current #</th><th>Due</th><th class="text-right">Unpaid Principal</th></tr></thead>
            <tbody>
                @foreach ($open as $i)<tr><td>#{{ $i->sequence_no }}</td><td>{{ App\Support\Format::date($i->due_date) }}</td><td class="text-right"><x-money :value="$i->principalOutstanding()" /></td></tr>@endforeach
            </tbody>
            <tfoot class="border-t border-line"><tr><td colspan="2" class="px-5 py-2 font-semibold">To reschedule</td><td class="px-5 py-2 text-right font-semibold"><x-money :value="$total" /></td></tr></tfoot>
        </table>
    </div>
    <div>
        <p class="form-label">New Installments</p>
        <div class="space-y-2">
            @foreach (range(0, 5) as $i)
                <div class="grid grid-cols-2 gap-2"><input type="date" name="lines[{{ $i }}][due_date]" class="form-control" aria-label="New due date {{ $i + 1 }}"><input name="lines[{{ $i }}][amount]" class="form-control" inputmode="decimal" placeholder="0.00" aria-label="New amount {{ $i + 1 }}"></div>
            @endforeach
        </div>
        <p class="form-error" data-field-error="lines" hidden></p>
    </div>
    <x-form.textarea name="reason" label="Reason" rows="2" required />
    <x-form.actions class="mt-0"><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit" icon="check">Request</x-button></x-form.actions>
</form>
