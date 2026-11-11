@php
    $money = fn ($v) => App\Support\Format::money($v);
    $pct = fn ($v) => App\Modules\Investors\Controllers\AgreementController::pct($v);
    $canProfit = $context->allows('investor.portal.profit.view');
    $toInvest = App\Support\Money::sub($summary['committed'], $summary['capital']);
    $waiting = array_filter(['Awaiting Approval' => $summary['pending'], 'Estimated' => $summary['estimated']], fn ($v) => App\Support\Money::isPositive($v));
@endphp
<x-layouts.portal title="Home" portal="investor">
    <x-page-header :title="$context->investor->name" :breadcrumbs="['Home' => null]" />

    <div @class(['mb-6 grid gap-4 sm:grid-cols-2', 'xl:grid-cols-4' => $canProfit])>
        <x-stat-card label="You Invested" :value="$money($summary['capital'])" icon="tag" tone="accent">
            <x-slot:meta><span class="text-ink-500">{{ App\Support\Money::isPositive($toInvest) ? $money($toInvest).' more to invest' : 'of '.$money($summary['committed']).' agreed' }}</span></x-slot:meta>
        </x-stat-card>
        @if ($canProfit)
            <x-stat-card label="Profit Received" :value="$money($summary['paid'])" icon="check" tone="success" />
            <x-stat-card label="Profit to Receive" :value="$money($summary['approved_due'])" icon="clock" tone="warning">
                @if (App\Support\Money::isPositive($summary['approved_due']))<x-slot:meta><span class="text-ink-500">Payment pending</span></x-slot:meta>
                @endif
            </x-stat-card>
            <x-stat-card label="Total Profit" :value="$money(App\Support\Money::add($summary['paid'], $summary['approved_due']))" icon="bar-chart" tone="info">
                @if ($waiting)<x-slot:meta><span class="text-ink-500">@foreach ($waiting as $label => $value){{ $loop->first ? '' : ' · ' }}{{ $label }} {{ $money($value) }}@endforeach</span></x-slot:meta>
                @endif
            </x-stat-card>
        @else
            <x-stat-card label="Investments" :value="$summary['active']" icon="door" tone="success" />
        @endif
    </div>

    <div class="grid items-start gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-4 xl:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-ink-900">My Investments</h2>
                @if (Route::has('investor.investments.index') && $investments->isNotEmpty())<a href="{{ route('investor.investments.index') }}" class="text-[13px] font-medium text-accent-700 hover:underline">View All</a>@endif
            </div>
            @forelse ($investments as $a)
                @php
                    $pos = $positions[$a->id];
                    $built = $progress[$a->project_id] ?? null;
                @endphp
                <a href="{{ route('investor.investments.show', $a->public_id) }}" class="card card-link block p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="icon-tile tone-accent"><x-icon name="door" size="h-6 w-6" /></span>
                            <div class="min-w-0">
                                <p class="truncate text-base font-semibold text-ink-900">{{ $a->project->name }} · Unit {{ $a->flat->flat_no }}</p>
                                <p class="text-[13px] text-ink-500">{{ $a->flat->building?->name }} · since {{ App\Support\Format::date($a->effective_from) }}</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 text-[13px] font-medium text-accent-700">Details<x-icon name="chevron-right" size="h-4 w-4" /></span>
                    </div>
                    <dl @class(['mt-4 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-line pt-4', 'sm:grid-cols-4' => $canProfit])>
                        <div><dt class="text-[13px] text-ink-500">Your Share of Profit</dt><dd class="text-lg font-semibold tabular-nums text-ink-900">{{ $pct($a->profit_share_percent) }}</dd></div>
                        <div><dt class="text-[13px] text-ink-500">You Invested</dt><dd class="text-lg font-semibold tabular-nums text-ink-900">{{ $money($pos['capital']) }}</dd><dd class="text-[12px] text-ink-500">of {{ $money($a->committed_capital) }} agreed</dd></div>
                        @if ($canProfit)
                            <div><dt class="text-[13px] text-ink-500">Profit Received</dt><dd class="text-lg font-semibold tabular-nums text-success-600">{{ $money($pos['paid']) }}</dd></div>
                            <div><dt class="text-[13px] text-ink-500">Profit to Receive</dt><dd class="text-lg font-semibold tabular-nums text-warning-600">{{ $money($pos['profit_due']) }}</dd></div>
                        @endif
                    </dl>
                    @if ($built !== null)
                        <div class="mt-4 border-t border-line pt-3">
                            <p class="text-[13px] text-ink-500">Construction of {{ $a->project->name }}</p>
                            <x-progress :value="$built" class="mt-1" />
                        </div>
                    @endif
                </a>
            @empty
                <x-card><x-empty-state icon="briefcase" title="No active investments" /></x-card>
            @endforelse
        </div>

        <x-card title="Recent Money In and Out" :padded="false" class="min-w-0">
            <x-slot:actions>@if (Route::has('investor.statements.index'))<a href="{{ route('investor.statements.index') }}" class="text-[13px] font-medium text-accent-700 hover:underline">Full Statement</a>@endif</x-slot:actions>
            @forelse ($movements as $m)
                <div class="flex items-start gap-3 border-b border-line px-5 py-3 last:border-0">
                    <span class="icon-tile icon-tile-sm tone-{{ $m['tone'] }} mt-0.5"><x-icon :name="$m['tone'] === 'success' ? 'arrow-down' : 'arrow-up'" size="h-4 w-4" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-baseline justify-between gap-3">
                            <span class="text-sm font-medium text-ink-900">{{ $m['title'] }}</span>
                            <span @class(['shrink-0 text-sm font-semibold tabular-nums', 'text-ink-400 line-through' => $m['state'] === 'Cancelled', 'text-ink-900' => $m['state'] !== 'Cancelled'])>{{ $money($m['amount']) }}</span>
                        </span>
                        <span class="block text-[13px] text-ink-500">{{ implode(' · ', array_filter([App\Support\Format::date($m['date']), $m['detail']])) }}</span>
                        <span class="mt-1 flex flex-wrap items-center gap-2">
                            @if ($m['state'])<x-badge tone="warning">{{ $m['state'] }}</x-badge>@endif
                            @if ($m['document'])<a href="{{ $m['document'] }}?format=pdf" class="inline-flex items-center gap-1 text-[13px] font-medium text-accent-700 hover:underline"><x-icon name="download" size="h-3.5 w-3.5" />{{ $m['tone'] === 'success' ? 'Payment Voucher' : 'Receipt' }}</a>@endif
                        </span>
                    </span>
                </div>
            @empty
                <x-empty-state icon="tag" title="No money movements yet" />
            @endforelse
        </x-card>
    </div>
</x-layouts.portal>
