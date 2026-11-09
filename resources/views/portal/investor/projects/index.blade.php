<x-layouts.portal title="Projects" portal="investor">
    <x-page-header title="Projects" :breadcrumbs="['Overview' => route('investor.dashboard'), 'Projects' => null]" />
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($projects as $p)
            <a href="{{ route('investor.projects.show', $p->public_id) }}" class="card block p-5 hover:shadow-md">
                <p class="font-semibold text-ink-900">{{ $p->name }}</p>
                <p class="text-[13px] text-ink-500">{{ $p->code }} · {{ $p->location_text }}</p>
                <p class="mt-4 text-sm text-ink-600">Construction</p>
                <x-progress :value="$p->overallProgress()" class="mt-1" />
            </a>
        @empty
            <x-empty-state icon="building" title="No projects" />
        @endforelse
    </div>
</x-layouts.portal>
