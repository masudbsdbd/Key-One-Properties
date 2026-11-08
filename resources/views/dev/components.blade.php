<x-layouts.app title="Components">
    <x-page-header title="UI Components" :breadcrumbs="['Development' => null, 'Components' => null]">
        <x-slot:actions>
            <x-button variant="secondary" icon="calendar">Nov 1, 2026 – Nov 8, 2026</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Projects" value="12" icon="building" />
        <x-stat-card label="Units (Flats)" value="384" icon="layers" />
        <x-stat-card label="Customers" value="146" icon="users" />
        <x-stat-card label="Departments" value="6" icon="briefcase" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-card title="Add Customer">
            <form action="{{ route('dev.components.validate') }}" method="POST" data-ajax-form class="space-y-4">
                @csrf
                <x-form.input name="full_name" label="Full Name" placeholder="Enter full name" required />
                <x-form.input name="phone" label="Phone" placeholder="17XX XXX XXX" icon="phone" required />
                <x-form.select name="source" label="Source" :options="['website' => 'Website', 'facebook' => 'Facebook', 'referral' => 'Referral', 'event' => 'Event', 'walk_in' => 'Walk-in', 'phone' => 'Phone Call', 'agent' => 'Agent', 'other' => 'Other']" placeholder="Select source" required />
                <x-form.textarea name="requirement" label="Requirement" placeholder="Write requirement..." />
                <x-form.actions class="mt-2">
                    <x-button variant="secondary" type="reset">Cancel</x-button>
                    <x-button type="submit" icon="check">Save</x-button>
                </x-form.actions>
            </form>
        </x-card>

        <x-card title="Components">
            <div class="space-y-5">
                <div>
                    <p class="form-label">Buttons</p>
                    <div class="flex flex-wrap gap-2">
                        <x-button>Primary</x-button>
                        <x-button variant="secondary">Secondary</x-button>
                        <x-button variant="outline">Outline</x-button>
                        <x-button variant="danger-soft">Danger</x-button>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-form.input name="demo_input" label="Input" placeholder="Input field" icon="user" />
                    <x-form.select name="demo_select" label="Select" :options="['a' => 'Option A', 'b' => 'Option B']" placeholder="Select option" />
                </div>
                <div>
                    <p class="form-label">Status Badges</p>
                    <div class="flex flex-wrap gap-2">
                        <x-badge tone="violet">New</x-badge>
                        <x-badge tone="info">Contacted</x-badge>
                        <x-badge tone="success">Qualified</x-badge>
                        <x-badge tone="warning">Won</x-badge>
                        <x-badge tone="danger">Lost</x-badge>
                    </div>
                </div>
                <div>
                    <p class="form-label">Icons</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['home', 'users', 'building', 'calendar', 'note', 'bar-chart', 'settings'] as $icon)
                            <span @class(['inline-flex h-11 w-11 items-center justify-center rounded-xl border', 'border-transparent bg-accent-500 text-white' => $loop->first, 'border-line text-ink-700' => ! $loop->first])><x-icon :name="$icon" /></span>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-card>

        <x-card title="Dialogs">
            <div class="flex flex-col items-center py-6 text-center">
                <span class="icon-tile tone-accent mb-4 h-20 w-20 rounded-full"><x-icon name="calendar-check" size="h-9 w-9" /></span>
                <p class="text-xl font-semibold text-ink-900">Schedule Site Visit</p>
                <div class="mt-6 grid w-full grid-cols-2 gap-3">
                    <x-button variant="secondary" data-modal-open="demo-modal">Open modal</x-button>
                    <x-button icon="calendar" data-modal-open="demo-drawer">Open drawer</x-button>
                </div>
            </div>
        </x-card>
    </div>

    <x-data-table class="mt-6" :url="route('dev.components.table')" sort="name" :columns="[
        ['label' => 'Name', 'sort' => 'name'],
        ['label' => 'Phone'],
        ['label' => 'Stage'],
        ['label' => '', 'class' => 'w-16'],
    ]">
        <x-slot:filters>
            <select class="form-control w-40" data-table-filter name="stage" aria-label="Stage">
                <option value="">All stages</option>
                <option>New</option>
                <option>Won</option>
            </select>
        </x-slot:filters>
    </x-data-table>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card title="Empty state" :padded="false">
            <x-empty-state icon="building" title="No projects yet">
                <x-button icon="plus">New Project</x-button>
            </x-empty-state>
        </x-card>
        <x-card title="Loading">
            <div class="space-y-3">
                <div class="skeleton h-4 w-2/3"></div>
                <div class="skeleton h-4 w-1/2"></div>
                <div class="skeleton h-24 w-full"></div>
            </div>
        </x-card>
    </div>

    @push('modals')
        <x-modal id="demo-modal" title="Edit Customer">
            <form action="{{ route('dev.components.validate') }}" method="POST" data-ajax-form class="space-y-4">
                @csrf
                <x-form.input name="full_name" id="modal-full-name" label="Full Name" required autofocus />
                <x-form.input name="phone" id="modal-phone" label="Phone" required />
                <x-form.actions>
                    <x-button variant="secondary" data-modal-close>Cancel</x-button>
                    <x-button type="submit">Save</x-button>
                </x-form.actions>
            </form>
        </x-modal>
        <x-drawer id="demo-drawer" title="Customer Details">
            <dl class="grid grid-cols-2 gap-5">
                <div><dt class="detail-label">Name</dt><dd class="detail-value">Sample Customer</dd></div>
                <div><dt class="detail-label">Phone</dt><dd class="detail-value">+880 1712 345 678</dd></div>
            </dl>
        </x-drawer>
    @endpush
</x-layouts.app>
