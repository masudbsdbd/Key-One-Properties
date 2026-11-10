@php
    $project ??= null;
    $warehouse ??= null;
    $today = now(config('app.display_timezone'))->toDateString();
@endphp
<form method="POST" action="{{ route('stock.issue') }}" data-ajax-form class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    @if ($project)<input type="hidden" name="project" id="issue-project" value="{{ $project->public_id }}">@endif
    <div class="grid gap-5 sm:grid-cols-3">
        @if ($project)
            <div class="sm:col-span-3"><p class="detail-label">Project</p><p class="detail-value">{{ $project->name }} · {{ $project->code }}</p></div>
        @endif
        <x-form.select name="warehouse" id="issue-warehouse" label="From Warehouse" :url="$project ? route('options.warehouses', ['issue_to' => $project->public_id]) : route('options.warehouses')"
            :options="$warehouse ? [$warehouse->public_id => $warehouse->name.' ('.$warehouse->code.')'] : []" :selected="$warehouse?->public_id" required placeholder="Search warehouses" />
        @unless ($project)
            <x-form.select name="project" id="issue-project" label="Project" :url="route('options.projects')" required placeholder="Search projects" />
        @endunless
        <x-form.input name="issued_at" type="date" label="Date" :value="$today" :max="$today" required />
        @if ($project)
            <x-form.select name="work_package" label="Work Package" :url="route('options.work-packages', ['project' => $project->public_id])" placeholder="None" />
        @else
            <x-form.select name="work_package" label="Work Package" :url="route('options.work-packages')" depends-on="#issue-project" depends-param="project" placeholder="None" />
        @endif
        <x-form.input name="purpose" label="Purpose" :wrapper-class="$project ? 'sm:col-span-3' : 'sm:col-span-2'" />
    </div>
    <x-line-items available-url="{{ route('stock.available') }}" warehouse-selector="#issue-warehouse" :lines="$lines ?? []" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Issue</x-button>
    </x-form.actions>
</form>
