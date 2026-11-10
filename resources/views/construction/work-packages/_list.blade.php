@php
    $canManage = auth()->user()->can('construction:manage', $project);
    $money = $spent !== null;
    $spentOf = fn ($p) => App\Support\Money::amount((string) ($spent[$p->id] ?? 0));
@endphp
@if ($packages->isEmpty())
    <x-empty-state icon="layers" title="No work packages yet" />
@else
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Work Package</th><th>Stage</th><th>Plan</th><th class="text-right">Budget</th>@if ($money)<th class="text-right">Spent</th>@endif<th class="w-44">Progress</th><th>Status</th><th class="w-24"></th></tr></thead>
            <tbody>
                @foreach ($packages as $package)
                    @php
                        $used = $money ? $spentOf($package) : null;
                        $left = $money ? App\Support\Money::sub($package->budget_amount, $used) : null;
                        $over = $money && App\Support\Money::isPositive($package->budget_amount) && App\Support\Money::cmp($left, 0) < 0;
                    @endphp
                    <tr>
                        <td><p class="font-medium text-ink-900">{{ $package->name }}</p><p class="text-[13px] text-ink-500">{{ $package->code }}@if ($package->parent) · under {{ $package->parent->code }}@endif</p></td>
                        <td><p class="text-ink-700">{{ $package->stage?->name ?? '—' }}</p>@if ($package->responsible)<p class="text-[13px] text-ink-500">{{ $package->responsible->name }}</p>@endif</td>
                        <td class="whitespace-nowrap text-ink-600">@if ($package->planned_start_at || $package->planned_end_at){{ App\Support\Format::date($package->planned_start_at) }} – {{ App\Support\Format::date($package->planned_end_at) }}@else — @endif</td>
                        <td class="text-right"><x-money :value="$package->budget_amount" :decimals="false" /></td>
                        @if ($money)
                            <td class="whitespace-nowrap text-right">
                                <p @class(['font-medium', 'text-danger-600' => $over, 'text-ink-900' => ! $over])><x-money :value="$used" /></p>
                                @if (App\Support\Money::isPositive($package->budget_amount))
                                    <p @class(['text-[13px]', 'text-danger-600' => $over, 'text-ink-500' => ! $over])>{{ App\Support\Format::money($over ? App\Support\Money::sub($used, $package->budget_amount) : $left) }} {{ $over ? 'over' : 'left' }}</p>
                                @endif
                            </td>
                        @endif
                        <td><x-progress :value="$package->progress_percent" /></td>
                        <td><x-status-badge :status="$package->status" /></td>
                        <td class="whitespace-nowrap text-right">
                            @if ($canManage)
                                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('construction.work-packages.edit', [$project, $package]) }}" data-size="max-w-3xl" aria-label="Edit {{ $package->name }}"><x-icon name="edit" size="h-4 w-4" /></button>
                                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('construction.work-packages.destroy', [$project, $package]) }}" data-confirm="Delete {{ $package->name }}?" data-confirm-text="Delete" aria-label="Delete {{ $package->name }}"><x-icon name="trash" size="h-4 w-4" /></button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @php $totalSpent = $money ? App\Support\Money::add('0', ...$packages->map($spentOf)->all()) : null; @endphp
                <tr class="font-semibold text-ink-900">
                    <td colspan="3">Total</td>
                    <td class="text-right"><x-money :value="App\Support\Money::add('0', ...$packages->pluck('budget_amount')->map(fn ($v) => (string) $v)->all())" :decimals="false" /></td>
                    @if ($money)<td class="text-right"><x-money :value="$totalSpent" /></td>@endif
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
