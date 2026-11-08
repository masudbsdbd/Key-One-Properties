<div class="rounded-card border border-line">
    <p class="border-b border-line bg-surface-muted px-4 py-2 text-[13px] font-medium text-ink-600">Will be applied to</p>
    <table class="data-table">
        <tbody>
            @foreach ($lines as $l)
                <tr>
                    <td>{{ match ($l['type']) { 'late_fee' => 'Late fee · #'.$l['item']->sequence_no, 'principal' => 'Installment #'.$l['item']->sequence_no.' · due '.App\Support\Format::date($l['item']->due_date), default => 'Advance (held for later dues)' } }}</td>
                    <td class="text-right"><x-money :value="$l['amount']" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
