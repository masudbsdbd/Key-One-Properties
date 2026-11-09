<x-layouts.app title="Availability">
    <x-page-header title="Availability" :breadcrumbs="['Dashboard' => route('dashboard'), 'Sales' => route('sales.overview'), 'Availability' => null]" />
    <x-card class="mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="w-72">
                <x-form.select name="project" id="board-project" label="Project" :url="route('options.projects')" :options="$project ? [$project->public_id => $project->name] : []" :selected="$project?->public_id" placeholder="Select a project" required />
            </div>
            <div class="flex flex-wrap gap-3 text-[13px] text-ink-600">
                @foreach (['not_released' => 'bg-ink-300', 'available' => 'bg-success-600', 'booked' => 'bg-info-600', 'sold' => 'bg-violet-600', 'unavailable' => 'bg-ink-500', 'maintenance' => 'bg-warning-600'] as $status => $dot)
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span>{{ App\Models\Flat::STATUSES[$status] }}</span>
                @endforeach
            </div>
        </div>
    </x-card>
    <div data-partial-source="#board-project" data-partial-base="{{ route('sales.board') }}" data-partial-param="project" data-partial-empty="Select a project">
        <div class="card p-5"><div class="skeleton h-40 w-full"></div></div>
    </div>
</x-layouts.app>
