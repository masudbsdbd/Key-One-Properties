@php
    $investorOptions = $agreement->investor ? [$agreement->investor->public_id => $agreement->investor->name.' · '.$agreement->investor->investor_code] : [];
    $flatOptions = $agreement->flat ? [$agreement->flat->public_id => $agreement->flat->project->code.' · '.$agreement->flat->building->code.' · Unit '.$agreement->flat->flat_no] : [];
    $locked = (bool) $agreement->supersedes_id;
@endphp
<form method="POST" action="{{ $agreement->exists ? route('investor-agreements.update', $agreement) : route('investor-agreements.store') }}" data-ajax-form @if ($agreement->exists) data-reload-on-success @endif class="space-y-5"
      data-agreement-form data-shares-url="{{ route('investor-agreements.shares') }}" data-agreement="{{ $agreement->public_id }}">
    @csrf
    @if ($agreement->exists) @method('PUT') @endif
    <div class="grid gap-5 sm:grid-cols-2">
        @if ($locked)
            <input type="hidden" name="investor" value="{{ $agreement->investor->public_id }}">
            <input type="hidden" name="flat" value="{{ $agreement->flat->public_id }}" data-agreement-flat>
            <div><p class="detail-label">Investor</p><p class="detail-value">{{ $agreement->investor->name }}</p></div>
            <div><p class="detail-label">Unit</p><p class="detail-value">{{ $flatOptions[$agreement->flat->public_id] }}</p></div>
        @else
            <x-form.select name="investor" label="Investor" :url="route('options.investors', ['active' => 1])" :options="$investorOptions" :selected="$agreement->investor?->public_id" required placeholder="Search investors" />
            <x-form.select name="flat" id="agreement-flat" label="Unit" :url="route('options.investment-flats')" :options="$flatOptions" :selected="$agreement->flat?->public_id" required placeholder="Search units" data-agreement-flat />
        @endif
    </div>
    <div data-share-gauge-host></div>
    <div class="grid gap-5 sm:grid-cols-4">
        <x-form.input name="profit_share_percent" label="Profit Share %" :value="$agreement->profit_share_percent !== null ? rtrim(rtrim((string) $agreement->profit_share_percent, '0'), '.') : ''" inputmode="decimal" required data-share-input />
        <x-form.input name="committed_capital" label="Committed Capital" :value="$agreement->committed_capital" inputmode="decimal" required />
        <x-form.input name="effective_from" type="date" label="Effective From" :value="$agreement->effective_from?->toDateString()" required data-share-date />
        <x-form.input name="effective_to" type="date" label="Effective To" :value="$agreement->effective_to?->toDateString()" data-share-date />
    </div>
    <x-form.textarea name="terms" label="Terms" rows="3" :value="$agreement->terms" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        @if ($agreement->exists)
            <x-button type="submit" icon="check">Save</x-button>
        @else
            <x-button type="submit" variant="secondary">Save Draft</x-button>
            @can('investor.agreement.edit')<x-button type="submit" name="submit" value="1" icon="arrow-right">Submit</x-button>@endcan
        @endif
    </x-form.actions>
</form>
