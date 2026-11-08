@php($unread = auth()->user()->unreadNotifications()->latest()->limit(8)->get())
<div class="relative">
    <button type="button" class="btn btn-ghost btn-icon relative h-11 w-11" data-menu-button aria-controls="menu-notifications" aria-expanded="false" aria-label="Notifications">
        <x-icon name="bell" size="h-6 w-6" />
        @if ($unread->isNotEmpty())<span class="absolute right-2.5 top-2.5 h-2.5 w-2.5 rounded-full bg-danger-500 ring-2 ring-surface"></span>@endif
    </button>
    <div id="menu-notifications" data-menu hidden class="absolute right-0 mt-2 w-80 rounded-card border border-line bg-surface shadow-raised">
        <p class="border-b border-line px-4 py-3 text-sm font-semibold text-ink-900">Notifications</p>
        @forelse ($unread as $notification)
            <div class="border-b border-line px-4 py-3 text-sm last:border-b-0">
                <p class="text-ink-800">{{ $notification->data['message'] ?? '' }}</p>
                <p class="mt-0.5 text-xs text-ink-500">{{ $notification->created_at->diffForHumans() }}</p>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-ink-500">No new notifications</p>
        @endforelse
    </div>
</div>
