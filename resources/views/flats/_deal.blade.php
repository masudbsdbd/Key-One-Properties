@use('App\Models\CustomerPayment')
@use('App\Models\CustomerRefund')
@use('App\Models\CustomerStatementEntry')
@use('App\Models\InstallmentScheduleItem')
@use('App\Models\InvestorFlatAgreement')
@use('App\Support\Format')
@use('App\Support\Money')
@php
    $unit = $deal['unit'];
    $sale = $deal['sale'];
    $customer = $deal['customer'];
    $position = $deal['position'];
    $tabs = array_filter([
        'installments' => $deal['items'] !== null ? ['Installments', $deal['items']->count()] : null,
        'payments' => $deal['payments'] !== null ? ['Payments', $deal['payments']->count()] : null,
        'ledger' => $deal['ledger'] !== null ? ['Ledger', $deal['ledger']->count()] : null,
        'refunds' => $deal['refunds']?->isNotEmpty() ? ['Refunds', $deal['refunds']->count()] : null,
        'investors' => $deal['investors']?->isNotEmpty() ? ['Investors', $deal['investors']->count()] : null,
        'history' => $deal['history']->isNotEmpty() ? ['Earlier Deals', $deal['history']->count()] : null,
    ]);
@endphp
<x-card class="mt-6 overflow-hidden" :padded="false">
    @if (! $unit)
        <x-empty-state icon="calendar" title="Not sold" class="border-b border-line py-10">
            @if ($deal['can_book'])
                <x-button size="sm" icon="plus" data-remote-modal="{{ route('bookings.create', ['flat' => $flat->public_id]) }}" data-size="max-w-3xl">New Booking</x-button>
            @endif
        </x-empty-state>
    @else
        <div class="grid gap-6 border-b border-line bg-gradient-to-br from-accent-50/70 via-surface to-surface p-5 sm:p-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <div class="flex min-w-0 items-start gap-4">
                <x-avatar :name="$customer->full_name" size="h-14 w-14 text-lg" />
                <div class="min-w-0">
                    <p class="detail-label">{{ $sale ? 'Sold To' : 'Booked By' }}</p>
                    <x-ref :to="$customer" class="block truncate text-lg font-semibold text-ink-900">{{ $customer->full_name }}</x-ref>
                    <p class="text-[13px] text-ink-500">{{ $customer->customer_code }} · {{ App\Support\Phone::display($customer->primary_mobile) }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-ref :to="$unit" class="inline-flex items-center gap-1.5 rounded-control border border-line bg-surface px-2.5 py-1 text-[13px] font-medium text-ink-900">
                            <x-icon :name="$sale ? 'file' : 'calendar'" size="h-3.5 w-3.5" />{{ $sale ? $sale->sale_no : $unit->booking_no }}
                        </x-ref>
                        <x-status-badge :status="$unit->status" :labels="$sale ? App\Models\FlatSale::STATUSES : App\Models\FlatBooking::STATUSES" />
                        <span class="text-[13px] text-ink-500">{{ Format::date($sale ? $sale->agreement_date : $unit->booking_date) }}</span>
                    </div>
                </div>
            </div>

            <div class="min-w-0">
                <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (array_filter([
                        [$sale ? 'Contract' : 'Agreed Price', $sale ? $sale->net_contract_value : $unit->agreed_price, 'text-ink-900'],
                        $position ? ['Paid', $position['paid'], 'text-success-600'] : null,
                        $position ? ['Balance', $position['balance'], 'text-ink-900'] : null,
                        $position ? ['Overdue', $position['overdue'], Money::isPositive($position['overdue']) ? 'text-danger-600' : 'text-ink-500'] : null,
                    ]) as [$label, $value, $tone])
                        <div class="rounded-control border border-line bg-surface px-3 py-2.5">
                            <dt class="text-[12px] text-ink-500">{{ $label }}</dt>
                            <dd class="mt-0.5 truncate font-semibold tabular-nums {{ $tone }}"><x-money :value="$value" /></dd>
                        </div>
                    @endforeach
                </dl>
                @if ($deal['progress'] !== null)
                    <x-progress :value="$deal['progress']" size="h-2.5" class="mt-4" />
                @endif
                @if ($position && $position['next'])
                    <p class="mt-2 text-[13px] text-ink-500">Next due {{ Format::date($position['next']->due_date) }} · <span class="font-medium text-ink-900"><x-money :value="$position['next']->outstanding()" /></span></p>
                @endif
            </div>
        </div>

        <ol class="grid gap-4 border-b border-line px-5 py-5 sm:grid-cols-5 sm:gap-2 sm:px-6">
            @foreach ($deal['steps'] as $i => $step)
                <li class="relative flex items-center gap-3 sm:flex-col sm:text-center">
                    @if (! $loop->last)<span class="absolute left-1/2 top-4 hidden h-0.5 w-full {{ $step['state'] === 'done' ? 'bg-success-600' : 'bg-ink-900/10' }} sm:block" aria-hidden="true"></span>@endif
                    <span @class([
                        'relative z-10 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[13px] font-semibold',
                        'bg-success-600 text-white' => $step['state'] === 'done',
                        'bg-accent-500 text-white ring-4 ring-accent-100' => $step['state'] === 'current',
                        'bg-surface text-ink-400 ring-1 ring-line' => $step['state'] === 'todo',
                    ])>@if ($step['state'] === 'done')<x-icon name="check" size="h-4 w-4" />@else{{ $i + 1 }}@endif</span>
                    <span class="min-w-0">
                        <span @class(['block text-sm font-medium', 'text-ink-900' => $step['state'] !== 'todo', 'text-ink-400' => $step['state'] === 'todo'])>{{ $step['label'] }}</span>
                        <span class="block text-[12px] text-ink-500">{{ $step['date'] ? Format::date($step['date']) : ($step['state'] === 'current' ? 'In progress' : '—') }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($tabs)
        <div class="flex gap-6 overflow-x-auto border-b border-line px-5 sm:px-6" role="tablist" data-hash="false">
            @foreach ($tabs as $key => [$label, $count])
                <button type="button" role="tab" class="tab" id="deal-tab-{{ $key }}" data-tab="deal-{{ $key }}" aria-controls="deal-panel-{{ $key }}">{{ $label }} <span class="ml-1 rounded-full bg-ink-900/5 px-1.5 text-[11px] font-semibold text-ink-500">{{ $count }}</span></button>
            @endforeach
        </div>

        @isset($tabs['installments'])
            <section id="deal-panel-installments" role="tabpanel" aria-labelledby="deal-tab-installments" class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Due Date</th><th class="text-right">Due</th><th class="text-right">Paid</th><th class="text-right">Outstanding</th><th>Status</th></tr></thead>
                    <tbody>
                        @php $n = 0; @endphp
                        @forelse ($deal['items'] as $item)
                            <tr @class(['opacity-50' => $item->status === 'rescheduled'])>
                                <td class="whitespace-nowrap font-medium text-ink-900">{{ $item->kind === 'down_payment' ? 'Down Payment' : 'Installment '.(++$n) }}</td>
                                <td class="whitespace-nowrap">{{ Format::date($item->due_date) }}</td>
                                <td class="text-right"><x-money :value="Money::add((string) $item->principal_due, (string) $item->fee_due)" /></td>
                                <td class="text-right text-success-600"><x-money :value="Money::add((string) $item->principal_paid, (string) $item->fee_paid)" /></td>
                                <td class="text-right font-medium"><x-money :value="$item->outstanding()" /></td>
                                <td><x-status-badge :status="$item->status" :labels="InstallmentScheduleItem::STATUSES" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty-state icon="calendar" title="No installment plan" class="py-8" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endisset

        @isset($tabs['payments'])
            <section id="deal-panel-payments" role="tabpanel" aria-labelledby="deal-tab-payments" class="overflow-x-auto" hidden>
                <table class="data-table">
                    <thead><tr><th>Payment</th><th>Method</th><th class="text-right">Amount</th><th>Status</th><th class="w-12"></th></tr></thead>
                    <tbody>
                        @forelse ($deal['payments'] as $p)
                            <tr>
                                <td class="whitespace-nowrap"><x-ref :to="$p" class="font-medium text-ink-900">{{ $p->payment_no }}</x-ref><p class="text-[13px] text-ink-500">{{ Format::date($p->payment_date) }}</p></td>
                                <td>{{ CustomerPayment::METHODS[$p->method] ?? $p->method }}@if ($p->reference_no)<p class="text-[13px] text-ink-500">{{ $p->reference_no }}</p>@endif</td>
                                <td @class(['text-right font-medium', 'line-through text-ink-400' => $p->status === 'reversed'])><x-money :value="$p->amount" /></td>
                                <td><x-status-badge :status="$p->status" :labels="CustomerPayment::STATUSES" /></td>
                                <td class="text-right">
                                    @if ($p->receipt && $p->status === 'posted')
                                        @can('collections.payment.receipt.view')
                                            <a href="{{ route('customer-payments.receipt', $p) }}" class="btn btn-ghost btn-icon h-8 w-8" aria-label="Receipt {{ $p->payment_no }}" target="_blank" rel="noopener"><x-icon name="printer" size="h-4 w-4" /></a>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="tag" title="No payments" class="py-8" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endisset

        @isset($tabs['ledger'])
            <section id="deal-panel-ledger" role="tabpanel" aria-labelledby="deal-tab-ledger" class="overflow-x-auto" hidden>
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Entry</th><th class="text-right">Charge</th><th class="text-right">Credit</th><th class="text-right">Balance</th></tr></thead>
                    <tbody>
                        @forelse ($deal['ledger'] as $e)
                            <tr>
                                <td class="whitespace-nowrap">{{ Format::date($e->entry_date) }}</td>
                                <td><span class="font-medium text-ink-900">{{ CustomerStatementEntry::TYPES[$e->entry_type] ?? $e->entry_type }}</span><p class="text-[13px] text-ink-500">{{ $e->description }}</p></td>
                                <td class="text-right">@if (Money::isPositive((string) $e->charge_amount))<x-money :value="$e->charge_amount" />@else<span class="text-ink-300">—</span>@endif</td>
                                <td class="text-right text-success-600">@if (Money::isPositive((string) $e->credit_amount))<x-money :value="$e->credit_amount" />@else<span class="text-ink-300">—</span>@endif</td>
                                <td class="text-right font-medium"><x-money :value="$e->running_balance" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="file" title="No entries" class="py-8" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endisset

        @isset($tabs['refunds'])
            <section id="deal-panel-refunds" role="tabpanel" aria-labelledby="deal-tab-refunds" class="overflow-x-auto" hidden>
                <table class="data-table">
                    <thead><tr><th>Refund</th><th class="text-right">Amount</th><th class="text-right">Deduction</th><th class="text-right">Payable</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($deal['refunds'] as $r)
                            <tr>
                                <td class="whitespace-nowrap"><x-ref :to="$r" class="font-medium text-ink-900">{{ $r->refund_no }}</x-ref><p class="text-[13px] text-ink-500">{{ Format::date($r->paid_on ?? $r->created_at) }}</p></td>
                                <td class="text-right"><x-money :value="$r->amount" /></td>
                                <td class="text-right text-ink-500"><x-money :value="$r->deduction_amount ?? '0'" /></td>
                                <td class="text-right font-medium"><x-money :value="$r->payable()" /></td>
                                <td><x-status-badge :status="$r->status" :labels="CustomerRefund::STATUSES" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endisset

        @isset($tabs['investors'])
            <section id="deal-panel-investors" role="tabpanel" aria-labelledby="deal-tab-investors" class="overflow-x-auto" hidden>
                <table class="data-table">
                    <thead><tr><th>Investor</th><th>Agreement</th><th class="text-right">Share</th><th class="text-right">Committed Capital</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($deal['investors'] as $a)
                            <tr>
                                <td><x-ref :to="$a->investor" class="font-medium text-ink-900">{{ $a->investor->name }}</x-ref><p class="text-[13px] text-ink-500">{{ $a->investor->investor_code }}</p></td>
                                <td class="whitespace-nowrap"><x-ref :to="$a">{{ $a->agreement_no }}</x-ref><p class="text-[13px] text-ink-500">v{{ $a->version }}</p></td>
                                <td class="text-right font-medium">{{ Format::percent($a->profit_share_percent) }}</td>
                                <td class="text-right"><x-money :value="$a->committed_capital" /></td>
                                <td><x-status-badge :status="$a->status" :labels="InvestorFlatAgreement::STATUSES" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="users" title="No investors" class="py-8" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endisset

        @isset($tabs['history'])
            <section id="deal-panel-history" role="tabpanel" aria-labelledby="deal-tab-history" class="overflow-x-auto" hidden>
                <table class="data-table">
                    <thead><tr><th>Deal</th><th>Customer</th><th class="text-right">Value</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($deal['history'] as $h)
                            @php $isSale = $h instanceof App\Models\FlatSale; @endphp
                            <tr>
                                <td class="whitespace-nowrap"><x-ref :to="$h" class="font-medium text-ink-900">{{ $isSale ? $h->sale_no : $h->booking_no }}</x-ref><p class="text-[13px] text-ink-500">{{ $isSale ? 'Sale' : 'Booking' }} · {{ Format::date($isSale ? $h->agreement_date : $h->booking_date) }}</p></td>
                                <td><x-ref :to="$h->customer">{{ $h->customer->full_name }}</x-ref><p class="text-[13px] text-ink-500">{{ $h->customer->customer_code }}</p></td>
                                <td class="text-right"><x-money :value="$isSale ? $h->net_contract_value : $h->agreed_price" /></td>
                                <td><x-status-badge :status="$h->status" :labels="$isSale ? App\Models\FlatSale::STATUSES : App\Models\FlatBooking::STATUSES" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endisset
    @endif
</x-card>
