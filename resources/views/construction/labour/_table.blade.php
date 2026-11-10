<x-data-table summary :selectable="auth()->user()->can('construction.labour.approve')" :url="route('labour.table', $project ? ['project' => $project->public_id] : [])" sort="date" dir="desc" search="Search entry number or notes" :columns="array_values(array_filter([
    ['label' => 'Entry', 'sort' => 'date'],
    $project ? null : ['label' => 'Project'],
    ['label' => 'Labour'],
    ['label' => 'Work Package'],
    ['label' => 'Amount', 'sort' => 'amount', 'class' => 'text-right'],
    ['label' => 'Status'],
    ['label' => '', 'class' => 'w-24'],
]))">
    <x-slot:filters>
        @unless ($project)
            <div class="w-56"><select name="project" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" class="form-control" aria-label="Project"><option value=""></option></select></div>
        @endunless
        <select name="status" class="form-control w-36" data-table-filter aria-label="Status">
            <option value="">All statuses</option>
            @foreach (App\Models\LabourEntry::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
        <div class="w-40">
            <select name="labour_type" class="form-control" data-table-filter data-select data-placeholder="All types" aria-label="Labour type">
                <option value=""></option>
                @foreach (App\Models\LabourType::names() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        <x-period />
    </x-slot:filters>
    <x-slot:bulk>
        <x-button size="sm" icon="check" data-bulk-action data-url="{{ route('labour.bulk-approve') }}" data-confirm="Approve the selected labour entries?" data-confirm-text="Approve" data-tone="primary">Approve</x-button>
    </x-slot:bulk>
    <x-slot:toolbar>
        @can('construction.cost.export')<x-export-menu :url="route('labour.export', $project ? ['project' => $project->public_id] : [])" />@endcan
        @if ($project)
            @can('construction:labour-manage', $project)
                <x-button size="sm" icon="plus" data-remote-modal="{{ route('labour.create', ['project' => $project->public_id]) }}" data-size="max-w-3xl">Record Labour</x-button>
            @endcan
        @endif
    </x-slot:toolbar>
</x-data-table>
