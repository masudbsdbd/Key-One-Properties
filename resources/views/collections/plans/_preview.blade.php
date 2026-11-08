<div class="rounded-card border border-line">
    <dl class="grid gap-3 border-b border-line bg-surface-muted p-4 text-sm sm:grid-cols-3">
        <div><dt class="text-ink-500">Contract Value</dt><dd class="font-semibold text-ink-900"><x-money :value="$built['contract_value']" /></dd></div>
        <div><dt class="text-ink-500">Booking Money Received</dt><dd class="font-semibold text-ink-900"><x-money :value="'-'.$built['booking_credit']" /></dd></div>
        <div><dt class="text-ink-500">To Schedule</dt><dd class="font-semibold text-ink-900"><x-money :value="$built['principal']" /></dd></div>
    </dl>
    <div class="max-h-64 overflow-y-auto">
        <table class="data-table">
            <thead><tr><th>#</th><th>Due Date</th><th>Type</th><th class="text-right">Amount</th></tr></thead>
            <tbody>
                @foreach ($built['items'] as $i => $item)
                    <tr><td class="text-ink-500">{{ $i + 1 }}</td><td>{{ App\Support\Format::date(\Illuminate\Support\Carbon::parse($item['due_date'])) }}</td><td>{{ $item['kind'] === 'down_payment' ? 'Down payment' : 'Installment' }}</td><td class="text-right"><x-money :value="$item['amount']" /></td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
