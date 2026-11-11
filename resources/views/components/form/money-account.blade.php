@props(['label' => 'Paid From', 'method' => '#f-payment-method', 'selected' => null, 'wrapperClass' => ''])
{{-- Accounts offered follow the payment method chosen in the field named by `method`. --}}
<x-form.select name="money_account" :id="'f-money-account-'.Illuminate\Support\Str::random(6)" :label="$label" :url="route('options.money-accounts')" :depends-on="$method" depends-param="method"
    :options="$selected ? [$selected->public_id => $selected->label()] : []" :selected="$selected?->public_id" placeholder="Select account"
    :required="App\Modules\Accounting\Support\AccountingSettings::enabled()" :wrapper-class="$wrapperClass" {{ $attributes }} />
