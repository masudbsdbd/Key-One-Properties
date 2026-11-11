<div class="grid gap-4 sm:grid-cols-2">
    @foreach (['in_hand' => ['In Hand', 'note', 'warning'], 'deposited' => ['Deposited, Waiting to Clear', 'bank', 'info']] as $status => [$label, $icon, $tone])
        <x-stat-card :label="$label.' · '.($totals[$status]->n ?? 0)" :value="App\Support\Format::money($totals[$status]->total ?? 0)" :icon="$icon" :tone="$tone" />
    @endforeach
</div>
