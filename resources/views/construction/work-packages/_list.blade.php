@php
    $canManage = auth()->user()->can('construction:manage', $project);
    $amount = fn ($v) => App\Support\Money::amount((string) $v);
    $outside = $money ? $amount($unassigned ?? 0) : '0.00';
@endphp
@if ($packages->isEmpty())
    <x-empty-state icon="layers" title="No work packages yet" />
@else
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Work Package</th><th>Stage</th><th>Plan</th><th class="text-right">Budget</th>@if ($money)<th class="text-right">Spent</th>@endif<th class="min-w-44">Progress</th><th class="w-24"></th></tr></thead>
            <tbody>
                @foreach ($packages as $package)
                    @php
                        $budget = $amount($package->effective_budget);
                        $used = $money ? $amount($package->spent) : null;
                        $left = $money ? App\Support\Money::sub($budget, $used) : null;
                        $over = $money && App\Support\Money::isPositive($budget) && App\Support\Money::cmp($left, 0) < 0;
                    @endphp
                    <tr>
                        <td><div class="flex gap-2" @if ($package->depth) style="padding-left: {{ min($package->depth, 4) * 20 }}px" @endif>
                            @if ($package->depth)<x-icon name="corner-down-right" size="h-4 w-4 mt-0.5 shrink-0 text-ink-400" />@endif
                            <div><p class="font-medium text-ink-900">{{ $package->name }}</p><p class="text-[13px] text-ink-500">{{ $package->code }}</p></div>
                        </div></td>
                        <td class="whitespace-nowrap"><p class="text-ink-700">{{ $package->stage?->name ?? '—' }}</p>@if ($package->responsible)<p class="text-[13px] text-ink-500">Responsible: {{ $package->responsible->name }}</p>@endif</td>
                        <td class="whitespace-nowrap text-ink-600">
                            @if ($package->planned_start_at || $package->planned_end_at)
                                <p>{{ App\Support\Format::date($package->planned_start_at) }} – {{ App\Support\Format::date($package->planned_end_at) }}</p>
                                @if ($package->planned_end_at && ! in_array($package->status, ['completed', 'cancelled'], true))
                                    <p @class(['text-[13px]', 'font-medium text-danger-600' => App\Support\Dates::daysUntil($package->planned_end_at) < 0, 'text-ink-500' => App\Support\Dates::daysUntil($package->planned_end_at) >= 0])>{{ App\Support\Dates::countdown($package->planned_end_at) }}</p>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right"><x-money :value="$budget" :decimals="false" /></td>
                        @if ($money)
                            <td class="whitespace-nowrap text-right">
                                <p @class(['font-medium', 'text-danger-600' => $over, 'text-ink-900' => ! $over])><x-money :value="$used" /></p>
                                @if (App\Support\Money::isPositive($budget))
                                    <p @class(['text-[13px]', 'text-danger-600' => $over, 'text-ink-500' => ! $over])>{{ App\Support\Format::money($over ? App\Support\Money::sub($used, $budget) : $left) }} {{ $over ? 'over' : 'left' }}</p>
                                @endif
                            </td>
                        @endif
                        <td><x-progress :value="$package->progress_percent" /><div class="mt-1.5"><x-status-badge :status="$package->status" /></div></td>
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
                <tr class="font-semibold text-ink-900">
                    <td colspan="3">{{ (float) $outside ? 'Work Packages' : 'Total' }}</td>
                    <td class="text-right"><x-money :value="$amount($totals['budget'])" :decimals="false" /></td>
                    @if ($money)<td class="text-right"><x-money :value="$amount($totals['spent'])" /></td>@endif
                    <td colspan="2"></td>
                </tr>
                @if ((float) $outside)
                    <tr class="text-ink-700">
                        <td colspan="4"><a href="#cost" class="ref-link">Cost with No Work Package</a></td>
                        <td class="text-right"><x-money :value="$outside" /></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="font-semibold text-ink-900">
                        <td colspan="4">Project Cost</td>
                        <td class="text-right"><x-money :value="App\Support\Money::add($amount($totals['spent']), $outside)" /></td>
                        <td colspan="2"></td>
                    </tr>
                @endif
            </tfoot>
        </table>
    </div>
@endif
