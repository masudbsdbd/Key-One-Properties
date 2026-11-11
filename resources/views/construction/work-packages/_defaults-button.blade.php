<x-button size="sm" variant="secondary" icon="layers" data-action="post" data-url="{{ route('construction.work-packages.defaults', $project) }}"
    data-confirm="Add {{ $count }} default work packages?" data-confirm-text="Add" data-tone="primary">Add Default Work Packages ({{ $count }})</x-button>
