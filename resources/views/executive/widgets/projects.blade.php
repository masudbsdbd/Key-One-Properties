<div class="grid grid-cols-2 gap-3">
    @forelse ($data['by_status'] as $label => $n)
        @include('executive.widgets._stat', ['label' => $label, 'value' => $n])
    @empty
        <div class="col-span-2"><x-empty-state icon="building" title="No projects" /></div>
    @endforelse
</div>
@if ($data['budget'])
    <p class="mb-2 mt-5 text-sm font-medium text-ink-900">Actual cost</p>
    <x-bars :items="collect($data['budget'])->mapWithKeys(fn ($p) => [$p['name'] => $p['actual']])->all()" />
@endif
<p class="mt-4 text-sm"><span @class(['font-semibold', 'text-danger-600' => $data['overdue_milestones'] > 0])>{{ $data['overdue_milestones'] }}</span> overdue milestones</p>
@include('executive.widgets._footer', ['basis' => 'Posted project cost'])
