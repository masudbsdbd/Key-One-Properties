@php
    $money = fn ($v) => App\Support\Format::money($v);
    $today = now(config('app.display_timezone'))->startOfDay();
    $items = $position['plan']?->items->sortBy('due_date') ?? collect();
@endphp
<x-layouts.portal title="Installments" portal="customer">
    <x-page-header title="Installments" :breadcrumbs="['Overview' => route('customer.dashboard'), 'Installments' => null]" />
    @if ($sales->count() > 1)
        <div class="mb-6 flex flex-wrap gap-2" role="tablist">
            @foreach ($sales as $s)
                <a href="{{ route('customer.installments.index', ['unit' => $s->public_id]) }}" @class(['view-switch border border-line', 'is-active' => $s->is($sale)]) @if ($s->is($sale)) aria-current="page" @endif>{{ $s->project->code }} · Unit {{ $s->flat->flat_no }}</a>
            @endforeach
        </div>
    @endif

    @if (! $sale)
        <x-empty-state icon="calendar" title="No installment plan" />
    @else
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Paid" :value="$money($position['paid'])" icon="check" tone="success" />
            <x-stat-card :label="App\Support\Money::cmp($position['balance'], '0') < 0 ? 'Advance' : 'Remaining'" :value="$money(ltrim($position['balance'], '-'))" icon="tag" tone="accent" />
            <x-stat-card label="Overdue" :value="$money($position['overdue'])" icon="alert" :tone="App\Support\Money::isPositive($position['overdue']) ? 'danger' : 'success'" />
            <x-stat-card label="Advance Credit" :value="$money($position['advance'])" icon="layers" tone="info" />
        </div>

        <x-card :title="'Schedule · Unit '.$sale->flat->flat_no" :padded="false">
            @if ($items->isEmpty())
                <x-empty-state icon="calendar" title="No installment plan yet" />
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Due</th><th class="text-right">Amount</th><th class="text-right">Late Fee</th><th class="text-right">Paid</th><th class="text-right">Remaining</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($items as $i)
                                @php $old = $i->status === 'rescheduled'; $late = ! $old && $i->isOpen() && $i->due_date->lt($today); @endphp
                                <tr @class(['opacity-60' => $old])>
                                    <td>{{ $i->sequence_no }}</td>
                                    <td class="whitespace-nowrap">{{ App\Support\Format::date($i->due_date) }}@if ($i->kind === 'down_payment')<span class="block text-[13px] text-ink-500">Down payment</span>@endif</td>
                                    <td class="text-right"><x-money :value="$i->principal_due" /></td>
                                    <td class="text-right">@if ((float) $i->fee_due)<x-money :value="$i->fee_due" />@else<span class="text-ink-400">—</span>@endif</td>
                                    <td class="text-right"><x-money :value="App\Support\Money::add((string) $i->principal_paid, (string) $i->fee_paid)" muted /></td>
                                    <td class="text-right font-medium">@if ($old)<span class="text-ink-400">—</span>@else<x-money :value="$i->outstanding()" />@endif</td>
                                    <td>@if ($old)<x-badge>Rescheduled</x-badge>@elseif ($late)<x-badge tone="danger">Overdue</x-badge>@else<x-status-badge :status="$i->status" :labels="App\Models\InstallmentScheduleItem::STATUSES" />@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <x-card title="Late Fees" :padded="false">
                @forelse ($fees as $f)
                    <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                        <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">Installment #{{ $f->item->sequence_no }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($f->created_at) }}</span></span>
                        <x-money :value="$f->amount" class="text-sm font-medium" />
                        <x-badge :tone="$f->status === 'waived' ? 'neutral' : 'warning'">{{ App\Models\LateFeeAssessment::STATUSES[$f->status] }}</x-badge>
                    </div>
                @empty
                    <x-empty-state icon="alert" title="No late fees" />
                @endforelse
            </x-card>
            <x-card title="Schedule Changes" :padded="false">
                @forelse ($reschedules as $r)
                    <div class="border-b border-line px-5 py-3 last:border-0">
                        <p class="text-sm font-medium text-ink-900">Rescheduled · {{ App\Support\Format::date($r->approved_at ?? $r->updated_at) }}</p>
                    </div>
                @empty
                    <x-empty-state icon="calendar" title="No schedule changes" />
                @endforelse
            </x-card>
        </div>
    @endif
</x-layouts.portal>
