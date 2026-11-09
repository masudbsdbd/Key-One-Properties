@php($rows = $receipts->flatMap(fn ($r) => App\Support\LineKeys::ordered($r->lines)->map(fn ($l, $i) => [$r, $l, $i])->filter(fn ($x) => $x[1]->billed_bill_id === null && App\Support\Money::isPositive($x[1]->returnable()))))
@if ($rows->isEmpty())
    <x-empty-state icon="download" title="No unbilled receipts" />
@else
    <div class="max-h-72 overflow-y-auto">
        <table class="data-table">
            <thead><tr><th class="w-10"><input type="checkbox" class="form-check" data-bill-all aria-label="Select all"></th><th>Receipt</th><th>Material</th><th class="text-right">Qty</th><th class="text-right">Unit Cost</th><th class="text-right">Amount</th></tr></thead>
            <tbody>
                @foreach ($rows as [$receipt, $line, $position])
                    <tr>
                        <td><input type="checkbox" name="receipt_lines[]" value="{{ $receipt->public_id }}:{{ $position }}" class="form-check" @checked(($checked ?? null) === $receipt->public_id) data-bill-line data-amount="{{ App\Support\Money::amount($line->billableAmount()) }}" aria-label="Bill {{ $line->material->name }} from {{ $receipt->grn_no }}"></td>
                        <td class="whitespace-nowrap"><p class="font-medium text-ink-900">{{ $receipt->grn_no }}</p><p class="text-[13px] text-ink-500">{{ $receipt->purchaseOrder->po_no }} · {{ App\Support\Format::date($receipt->received_at) }}</p></td>
                        <td>{{ $line->material->name }}</td>
                        <td class="text-right tabular-nums">{{ App\Support\Format::qty($line->returnable()) }} {{ $line->material->unit->code }}</td>
                        <td class="text-right"><x-money :value="$line->unit_cost" /></td>
                        <td class="text-right font-medium"><x-money :value="$line->billableAmount()" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
