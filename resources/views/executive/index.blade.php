@php
    $titles = ['projects' => 'Projects', 'units' => 'Units', 'sales' => 'Sales', 'collections' => 'Collections', 'construction' => 'Construction Cost', 'inventory' => 'Inventory', 'investors' => 'Investors', 'payables' => 'Payables', 'exceptions' => 'Needs Attention'];
@endphp
<x-layouts.app title="Executive Dashboard">
    <x-page-header title="Executive Dashboard" :breadcrumbs="['Dashboard' => route('dashboard'), 'Executive' => null]">
        <x-slot:actions>@can('reports.view')<x-button variant="secondary" icon="file" href="{{ route('reports.index') }}">Reports</x-button>@endcan</x-slot:actions>
    </x-page-header>
    <div data-executive data-widget-base="{{ url('executive/widgets') }}">
        <div class="card mb-6 flex flex-col gap-3 p-4 md:flex-row md:flex-wrap md:items-center">
            <div class="w-full md:w-60"><select name="project" class="form-control" data-exec-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option>@if ($project)<option value="{{ $project->public_id }}" selected>{{ $project->name }}</option>@endif</select></div>
            <select class="form-control md:w-40" data-period-preset aria-label="Period"><option value="custom">Custom</option><option value="this-month">This month</option><option value="last-month">Last month</option><option value="this-quarter">This quarter</option><option value="this-year">This year</option></select>
            <input type="date" name="from" value="{{ $period[0]->toDateString() }}" class="form-control md:w-40" data-exec-filter aria-label="From">
            <input type="date" name="to" value="{{ $period[1]->toDateString() }}" class="form-control md:w-40" data-exec-filter aria-label="To">
            <x-badge class="md:ml-auto">Operational · not audited · BDT</x-badge>
        </div>
        <div class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach ($widgets as $w)
                <x-card :title="$titles[$w]" data-widget="{{ $w }}" aria-live="polite">
                    <div data-widget-body><div class="skeleton h-40 w-full"></div></div>
                </x-card>
            @endforeach
        </div>
    </div>
</x-layouts.app>
