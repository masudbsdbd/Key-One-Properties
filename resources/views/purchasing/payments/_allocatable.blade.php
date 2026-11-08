<div class="flex items-center justify-between border-b border-line px-5 py-3 text-sm"><span class="text-ink-500">Payable</span><x-money :value="$balance" class="font-semibold text-ink-900" /></div>
@if ($bills->isEmpty())
    <x-empty-state icon="file" title="No open bills" />
@else
    <div class="max-h-72 overflow-y-auto">
        <table class="data-table">
            <thead><tr><th>Bill</th><th>Due</th><th class="text-right">Outstanding</th><th class="w-40">Pay</th></tr></thead>
            <tbody>
                @foreach ($bills as $bill)
                    @php($due = App\Support\Money::amount($bill->outstanding()))
                    <tr>
                        <td class="whitespace-nowrap"><p class="font-medium text-ink-900">{{ $bill->bill_no }}</p><p class="text-[13px] text-ink-500">{{ $bill->supplier_invoice_no ?: App\Support\Format::date($bill->invoice_date) }}</p></td>
                        <td class="whitespace-nowrap {{ $bill->due_date?->isPast() ? 'text-danger-600' : 'text-ink-700' }}">{{ App\Support\Format::date($bill->due_date) }}</td>
                        <td class="text-right"><x-money :value="$due" /></td>
                        <td data-field>
                            <div class="flex gap-1">
                                <input name="allocations[{{ $bill->public_id }}]" class="form-control h-9" inputmode="decimal" placeholder="0.00" aria-label="Pay {{ $bill->bill_no }}" data-allocation data-max="{{ $due }}">
                                <button type="button" class="btn btn-ghost btn-icon h-9 w-9" data-allocate-full aria-label="Pay full {{ $bill->bill_no }}"><x-icon name="check" size="h-4 w-4" /></button>
                            </div>
                            <p class="form-error" data-error hidden></p>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
