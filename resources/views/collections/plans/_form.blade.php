@php
    $today = now(config('app.display_timezone'))->toDateString();
    $saleOptions = $sale ? [$sale->public_id => $sale->sale_no.' · '.$sale->customer->full_name.' · Unit '.$sale->flat->flat_no] : [];
@endphp
<form method="POST" action="{{ route('installment-plans.store') }}" data-ajax-form class="space-y-5" data-plan-form data-preview-url="{{ route('installment-plans.preview') }}">
    @csrf
    <x-form.select name="sale" label="Sale" :url="route('options.plan-sales')" :options="$saleOptions" :selected="$sale?->public_id" required placeholder="Search approved sales" />
    <div class="grid gap-5 sm:grid-cols-4">
        <x-form.input name="start_date" type="date" label="Down Payment Due" :value="$today" required />
        <x-form.input name="down_payment_amount" label="Down Payment" value="0" inputmode="decimal" />
        <x-form.select name="installment_frequency" label="Frequency" :options="App\Models\InstallmentPlan::FREQUENCIES" selected="monthly" required :enhance="false" data-plan-frequency />
        <x-form.input name="count" type="number" label="Installments" value="12" min="1" max="240" data-plan-regular />
        <x-form.input name="first_due_date" type="date" label="First Installment" :value="now(config('app.display_timezone'))->addMonth()->toDateString()" data-plan-regular wrapper-class="sm:col-span-2" />
    </div>
    <div data-plan-custom hidden>
        <p class="form-label">Installments</p>
        <div class="space-y-2" data-plan-lines>
            @foreach (range(0, 5) as $i)
                <div class="grid grid-cols-2 gap-2"><input type="date" name="lines[{{ $i }}][due_date]" class="form-control" aria-label="Due date {{ $i + 1 }}"><input name="lines[{{ $i }}][amount]" class="form-control" inputmode="decimal" placeholder="0.00" aria-label="Amount {{ $i + 1 }}"></div>
            @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-secondary mt-2" data-plan-add-line><x-icon name="plus" size="h-4 w-4" />Add line</button>
        <p class="form-error" data-field-error="lines" hidden></p>
    </div>
    <div class="flex justify-end"><x-button variant="secondary" icon="eye" data-plan-preview>Preview</x-button></div>
    <div data-plan-output></div>
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" variant="secondary">Save Draft</x-button>
        <x-button type="submit" name="submit" value="1" icon="arrow-right">Submit</x-button>
    </x-form.actions>
</form>
