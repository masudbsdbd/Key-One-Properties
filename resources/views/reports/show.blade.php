@php
    $filters = $report->filters();
    $cols = collect($report->columns())->map(fn ($type, $label) => ['label' => $label, 'class' => in_array($type, ['money', 'qty', 'number'], true) ? 'text-right' : ''])->values()->all();
@endphp
<x-layouts.app :title="$report->name()">
    <x-page-header :title="$report->name()" :breadcrumbs="['Dashboard' => route('dashboard'), 'Reports' => route('reports.index'), $report->name() => null]" />
    <div class="mb-4 flex flex-wrap items-center gap-2 text-[13px] text-ink-600">
        <x-badge tone="info">{{ $report->basis() }}</x-badge>
        <x-badge>BDT</x-badge>
        <x-badge>Operational · not audited</x-badge>
        <span>Updated {{ now(config('app.display_timezone'))->format('d M Y, h:i A') }}</span>
    </div>
    <x-data-table :url="route('reports.table', $report->key())" :search="$report->search() ? 'Search' : false" :columns="$cols" data-table-history>
        <x-slot:filters>
            @if (in_array('project', $filters, true))
                <div class="w-56"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option>@if ($p = App\Modules\Reports\BaseReport::project(request(), auth()->user()))<option value="{{ $p->public_id }}" selected>{{ $p->name }}</option>@endif</select></div>
            @endif
            @if (in_array('period', $filters, true))
                <select class="form-control w-36" data-period-preset aria-label="Period">
                    <option value="">Any period</option><option value="this-month">This month</option><option value="last-month">Last month</option><option value="this-quarter">This quarter</option><option value="this-year">This year</option><option value="custom">Custom</option>
                </select>
                <input type="date" name="from" class="form-control w-40" data-table-filter aria-label="From">
                <input type="date" name="to" class="form-control w-40" data-table-filter aria-label="To">
            @endif
            @if (in_array('status', $filters, true) && $report->statuses())
                <select name="status" class="form-control w-40" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach ($report->statuses() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            @endif
            @if (in_array('category', $filters, true))
                <select name="category" class="form-control w-40" data-table-filter aria-label="Category"><option value="">All categories</option>@foreach (App\Models\ProjectCostAllocation::CATEGORIES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            @endif
            @if (in_array('scope', $filters, true))
                <select name="scope" class="form-control w-36" data-table-filter aria-label="Scope"><option value="">Shared and direct</option><option value="shared">Shared</option><option value="direct">Unit-tagged</option></select>
            @endif
            @if (in_array('scope_expense', $filters, true))
                <select name="scope" class="form-control w-36" data-table-filter aria-label="Scope"><option value="">All scopes</option>@foreach (App\Models\Expense::SCOPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            @endif
            @foreach (['warehouse' => ['options.warehouses', 'All warehouses'], 'supplier' => ['options.suppliers', 'All suppliers'], 'contractor' => ['options.contractors', 'All contractors'], 'investor' => ['options.investors', 'All investors']] as $f => [$route, $placeholder])
                @if (in_array($f, $filters, true) && Route::has($route))
                    <div class="w-52"><select name="{{ $f }}" class="form-control" data-table-filter data-select data-select-url="{{ route($route) }}" data-placeholder="{{ $placeholder }}" aria-label="{{ ucfirst($f) }}"><option value=""></option></select></div>
                @endif
            @endforeach
            @if (in_array('module', $filters, true))
                <select name="module" class="form-control w-40" data-table-filter aria-label="Module"><option value="">All modules</option>@foreach (Spatie\Activitylog\Models\Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter() as $m)<option value="{{ $m }}">{{ Illuminate\Support\Str::headline($m) }}</option>@endforeach</select>
            @endif
        </x-slot:filters>
        <x-slot:toolbar>@can('reports.export')<x-export-menu :url="route('reports.export', $report->key())" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
