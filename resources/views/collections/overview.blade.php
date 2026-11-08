<x-layouts.app title="Collections">
    <x-page-header title="Collections" :breadcrumbs="['Dashboard' => route('dashboard'), 'Collections' => null]">
        <x-slot:actions><x-button variant="secondary" icon="clock" href="{{ route('dues.index') }}">Dues & Overdue</x-button></x-slot:actions>
    </x-page-header>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Overdue" :value="App\Support\Format::money($overdue, false)" icon="alert" tone="warning" :href="route('dues.index', ['filter' => 'overdue'])" />
        <x-stat-card label="Due This Month" :value="App\Support\Format::money($dueThisMonth, false)" icon="calendar" tone="info" :href="route('dues.index', ['filter' => 'month'])" />
        <x-stat-card label="Collected This Month" :value="App\Support\Format::money($collectedThisMonth, false)" icon="check" tone="success" :href="route('customer-payments.index')" />
        <x-stat-card label="Waiting to Post" :value="number_format($pendingPayments)" icon="clock" tone="accent" :href="route('customer-payments.index', ['status' => 'pending'])" />
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_1.2fr]">
        <x-card title="Overdue Aging">
            @include('collections.dues._aging', ['aging' => $aging])
            <p class="mt-4 flex justify-between border-t border-line pt-3 text-sm"><span class="text-ink-500">Unapplied advances</span><x-money :value="$advances" class="font-medium text-ink-900" /></p>
        </x-card>
        <x-card title="Recent Payments" :padded="false">
            @forelse ($recent as $p)
                <a href="{{ route('customer-payments.show', $p) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0 hover:bg-surface-muted">
                    <span class="min-w-0 flex-1"><span class="block font-medium text-ink-900">{{ $p->payment_no }} · {{ $p->customer->full_name }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($p->payment_date) }} · Unit {{ ($p->sale ?? $p->booking)->flat->flat_no }}</span></span>
                    <x-money :value="$p->amount" class="text-sm font-medium" />
                    <x-status-badge :status="$p->status" />
                </a>
            @empty
                <x-empty-state icon="tag" title="No payments yet" />
            @endforelse
        </x-card>
    </div>
</x-layouts.app>
