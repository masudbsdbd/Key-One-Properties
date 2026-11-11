@php
    $asOf = $filters['asOf'] ?? false;
    $routeName = Route::currentRouteName();
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :breadcrumbs="['Dashboard' => route('dashboard'), 'Accounting' => route('accounting.overview'), $title => null]" />

    <form method="GET" action="{{ route($routeName) }}" data-report-filters class="mb-5 flex flex-wrap items-end gap-3">
        @if ($filters['account'] ?? false)
            <div class="w-80">
                <label for="report-account" class="form-label">Account</label>
                <select id="report-account" name="account" class="form-control" data-select data-select-url="{{ route('options.ledger-accounts') }}" data-placeholder="Select account">
                    <option value=""></option>
                    @if ($account)<option value="{{ $account->public_id }}" selected>{{ $account->label() }}</option>@endif
                </select>
            </div>
        @endif
        @if ($asOf)
            <input type="hidden" name="period" value="custom">
            <div><label for="report-to" class="form-label">As Of</label><input type="date" id="report-to" name="to" value="{{ $range['to'] }}" class="form-control w-44"></div>
        @else
            <div>
                <label for="report-period" class="form-label">Period</label>
                <select id="report-period" name="period" class="form-control w-48" data-report-period>
                    @foreach (App\Modules\Accounting\Services\Statements::PERIODS as $value => $label)<option value="{{ $value }}" @selected($range['preset'] === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div data-report-dates @if ($range['preset'] !== 'custom') hidden @endif class="flex items-end gap-3">
                <div><label for="report-from" class="form-label">From</label><input type="date" id="report-from" name="from" value="{{ $range['from'] }}" class="form-control w-44"></div>
                <div><label for="report-to" class="form-label">To</label><input type="date" id="report-to" name="to" value="{{ $range['to'] }}" class="form-control w-44"></div>
            </div>
        @endif
        @if ($filters['project'] ?? false)
            <div class="w-64">
                <label for="report-project" class="form-label">Project</label>
                <select id="report-project" name="project" class="form-control" data-select data-placeholder="All projects">
                    <option value=""></option>
                    @foreach ($projects as $p)<option value="{{ $p->public_id }}" @selected(($project ?? null)?->id === $p->id)>{{ $p->code }} · {{ $p->name }}</option>@endforeach
                </select>
            </div>
        @endif
        @if ($filters['compare'] ?? false)
            <label class="inline-flex h-10 cursor-pointer select-none items-center gap-2.5 text-sm text-ink-700"><input type="checkbox" name="compare" value="1" class="form-check" @checked(request()->boolean('compare'))><span>Compare with the period before</span></label>
        @endif
    </form>

    <div data-report-body>@include('accounting.reports._body')</div>
</x-layouts.app>
