@php $money = fn ($v) => App\Support\Format::money($v); @endphp
<x-layouts.portal title="Overview" portal="customer">
    <x-page-header :title="$context->customer->full_name" :breadcrumbs="['Overview' => null]" />

    @if ($next)
        <div class="mb-6 flex flex-wrap items-center gap-4 rounded-card border border-accent-200 bg-accent-50 px-5 py-4">
            <span class="icon-tile icon-tile-md tone-accent bg-white/70"><x-icon name="calendar" size="h-6 w-6" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm text-ink-600">Next Installment · Unit {{ $next['unit']->flat->flat_no }}</p>
                <p class="whitespace-nowrap text-2xl font-semibold tabular-nums text-ink-900">{{ $money($next['item']->outstanding()) }}</p>
            </div>
            <div class="w-full border-t border-accent-200 pt-3 sm:w-auto sm:border-0 sm:pt-0 sm:text-right">
                <p class="text-sm text-ink-600">Due</p>
                <p @class(['text-lg font-semibold', 'text-danger-600' => $next['item']->due_date->lt(now(config('app.display_timezone'))->startOfDay()), 'text-ink-900' => ! $next['item']->due_date->lt(now(config('app.display_timezone'))->startOfDay())])>{{ App\Support\Format::date($next['item']->due_date) }}</p>
            </div>
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total Contract" :value="$money($totals['contract'])" icon="file" tone="info" />
        <x-stat-card label="Paid" :value="$money($totals['paid'])" icon="check" tone="success" />
        <x-stat-card :label="App\Support\Money::cmp($totals['balance'], '0') < 0 ? 'Advance' : 'Remaining'" :value="$money(ltrim($totals['balance'], '-'))" icon="tag" tone="accent" />
        <x-stat-card label="Overdue" :value="$money($totals['overdue'])" icon="alert" :tone="App\Support\Money::isPositive($totals['overdue']) ? 'danger' : 'success'" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 xl:col-span-2">
            <div class="grid gap-4 sm:grid-cols-2">
                @forelse ($units as $entry)
                    @include('portal.customer._unit-card')
                @empty
                    <div class="sm:col-span-2"><x-empty-state icon="door" title="No units" /></div>
                @endforelse
            </div>
        </div>
        @if ($recent->isNotEmpty() || Route::has('customer.payments.index'))
            <x-card title="Recent Payments" :padded="false">
                @forelse ($recent as $p)
                    <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                        <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">{{ $p->payment_no }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($p->payment_date) }} · Unit {{ ($p->sale ?? $p->booking)->flat->flat_no }}</span></span>
                        <x-money :value="$p->amount" class="text-sm font-medium" />
                        @if ($p->receipt && $context->allows('customer.portal.statements.download'))<x-document-menu :url="route('customer.receipts.show', $p->public_id)" compact />@endif
                    </div>
                @empty
                    <x-empty-state icon="tag" title="No payments yet" />
                @endforelse
            </x-card>
        @endif
    </div>
</x-layouts.portal>
