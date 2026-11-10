<x-activity-feed :entries="$entries" />
@if ($more ?? null)
    <div class="border-t border-line px-5 py-3 text-center"><button type="button" class="btn btn-ghost btn-sm" data-partial-more="{{ $more }}">Show Older</button></div>
@endif
