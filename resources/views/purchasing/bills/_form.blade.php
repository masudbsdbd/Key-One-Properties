@php
    $today = now(config('app.display_timezone'))->toDateString();
    $supplierOptions = $supplier ? [$supplier->public_id => $supplier->name.' · '.$supplier->supplier_code] : [];
@endphp
<form method="POST" action="{{ route('supplier-bills.store') }}" data-ajax-form class="space-y-5" data-bill-form>
    @csrf
    <div class="grid gap-5 sm:grid-cols-4">
        <x-form.select name="supplier" id="bill-supplier" label="Supplier" :url="route('options.suppliers')" :options="$supplierOptions" :selected="$supplier?->public_id" required placeholder="Search suppliers" wrapper-class="sm:col-span-2" />
        <x-form.input name="supplier_invoice_no" label="Supplier Invoice No." wrapper-class="sm:col-span-2" />
        <x-form.input name="invoice_date" type="date" label="Invoice Date" :value="$today" :max="$today" required />
        <x-form.input name="due_date" type="date" label="Due Date" />
    </div>

    <div>
        <p class="form-label">Received Goods</p>
        <div class="rounded-card border border-line" data-partial-source="#bill-supplier" data-partial-base="{{ route('supplier-bills.billable', array_filter(['receipt' => $receipt?->public_id])) }}" data-partial-param="supplier" data-partial-empty="Select a supplier"><p class="p-5 text-sm text-ink-500">Select a supplier</p></div>
        <p class="form-error" data-field-error="receipt_lines" hidden></p>
    </div>

    <div>
        <p class="form-label">Other Charges</p>
        <div class="space-y-2">
            @foreach (range(0, 2) as $i)
                <div class="grid gap-2 sm:grid-cols-[1fr_180px]">
                    <div data-field><input name="charges[{{ $i }}][description]" class="form-control" placeholder="{{ ['Transport', 'Unloading', 'Other'][$i] }}" aria-label="Charge description {{ $i + 1 }}"><p class="form-error" data-error hidden></p></div>
                    <div data-field><input name="charges[{{ $i }}][amount]" class="form-control" inputmode="decimal" placeholder="0.00" aria-label="Charge amount {{ $i + 1 }}" data-bill-amount><p class="form-error" data-error hidden></p></div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-[1fr_180px] sm:items-end">
        <x-form.textarea name="notes" label="Notes" rows="2" />
        <x-form.input name="tax_amount" label="VAT / Tax" value="0" inputmode="decimal" data-bill-amount />
    </div>
    <div class="flex justify-end text-sm text-ink-600">Total<span class="ml-2 font-semibold tabular-nums text-ink-900" data-bill-total>৳ 0.00</span></div>

    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save Draft</x-button>
    </x-form.actions>
</form>
