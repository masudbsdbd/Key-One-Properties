<x-layouts.app :title="$customer->full_name">
    <x-page-header :title="$customer->full_name" :breadcrumbs="['Dashboard' => route('dashboard'), 'Customers' => route('customers.index'), $customer->customer_code => null]">
        <x-slot:actions>
            @if (Route::has('customers.statement'))@can('collections.payment.view')<x-button variant="secondary" icon="file" href="{{ route('customers.statement', $customer) }}">Statement</x-button>@endcan @endif
            @can('update', $customer)
                <x-button icon="edit" data-remote-modal="{{ route('customers.edit', $customer) }}" data-size="max-w-3xl">Edit</x-button>
            @endcan
            @can('archive', $customer)
                <x-button variant="ghost" icon="archive" data-action="delete" data-url="{{ route('customers.destroy', $customer) }}" data-confirm="Archive {{ $customer->full_name }}?" data-confirm-text="Archive" aria-label="Archive customer" />
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-[1fr_2fr]">
        <x-card>
            <div class="flex items-center gap-4">
                <x-avatar :name="$customer->full_name" size="h-16 w-16 text-xl" />
                <div class="min-w-0">
                    <p class="truncate text-lg font-semibold text-ink-900">{{ $customer->full_name }}</p>
                    <p class="text-sm text-ink-500">{{ $customer->customer_code }}</p>
                    <div class="mt-1.5 flex gap-1.5">
                        <x-badge :tone="$customer->status === 'active' ? 'success' : 'neutral'">{{ App\Models\Customer::STATUSES[$customer->status] }}</x-badge>
                        @if ($customer->source)<x-badge :tone="App\Models\Customer::SOURCE_TONES[$customer->source] ?? 'neutral'">{{ App\Models\Customer::SOURCES[$customer->source] }}</x-badge>@endif
                    </div>
                </div>
            </div>
            <dl class="mt-6 space-y-4 border-t border-line pt-5">
                <div class="flex items-start gap-3"><x-icon name="phone" size="h-4 w-4 mt-0.5 text-ink-400" /><div><dt class="sr-only">Mobile</dt><dd class="text-sm text-ink-900">{{ App\Support\Phone::display($customer->primary_mobile) }}</dd>@if ($customer->alternate_mobile)<dd class="text-[13px] text-ink-500">{{ App\Support\Phone::display($customer->alternate_mobile) }}</dd>@endif</div></div>
                @if ($customer->email)
                    <div class="flex items-start gap-3"><x-icon name="mail" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">Email</dt><dd class="text-sm text-ink-900 break-all">{{ $customer->email ?: '—' }}</dd></div>
                @endif
                @if ($customer->occupation)
                    <div class="flex items-start gap-3"><x-icon name="briefcase" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">Occupation</dt><dd class="text-sm text-ink-900">{{ $customer->occupation ?: '—' }}</dd></div>
                @endif
                @if ($customer->date_of_birth)
                    <div class="flex items-start gap-3"><x-icon name="calendar" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">Date of birth</dt><dd class="text-sm text-ink-900">{{ App\Support\Format::date($customer->date_of_birth) }}</dd></div>
                @endif
                @if ($customer->nid_or_passport)
                <div class="flex items-start gap-3">
                    <x-icon name="id-card" size="h-4 w-4 mt-0.5 text-ink-400" />
                    <dt class="sr-only">NID / Passport</dt>
                    <dd class="flex items-center gap-2 text-sm text-ink-900">
                            <span class="font-mono" data-identity-value>{{ auth()->user()->can('viewSensitive', $customer) ? $customer->maskedIdentity() : '••••••••' }}</span>
                            @can('viewSensitive', $customer)
                                <button type="button" class="btn btn-ghost btn-sm h-7 px-2" data-reveal-identity data-url="{{ route('customers.identity', $customer) }}"><x-icon name="eye" size="h-4 w-4" />Show</button>
                            @endcan
                    </dd>
                </div>
                @endif
            </dl>
            <p class="mt-6 border-t border-line pt-4 text-[13px] text-ink-500">{{ $customer->creator?->name }} · {{ App\Support\Format::datetime($customer->created_at) }}</p>
        </x-card>

        <div class="min-w-0">
            <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
                <button type="button" role="tab" class="tab" id="tab-addresses" data-tab="addresses" aria-controls="panel-addresses">Addresses</button>
                <button type="button" role="tab" class="tab" id="tab-notes" data-tab="notes" aria-controls="panel-notes">Notes</button>
                @can('manageDocuments', $customer)
                    <button type="button" role="tab" class="tab" id="tab-documents" data-tab="documents" aria-controls="panel-documents">Documents</button>
                @endcan
                @canany(['sales.sale.view', 'sales.booking.view'])<button type="button" role="tab" class="tab" id="tab-sales" data-tab="sales" aria-controls="panel-sales">Sales</button>@endcanany
                @can('collections.payment.view')<button type="button" role="tab" class="tab" id="tab-payments" data-tab="payments" aria-controls="panel-payments">Payments</button>@endcan
                @canany(['portal.links.manage', 'portal.grants.manage'])<button type="button" role="tab" class="tab" id="tab-portal" data-tab="portal" aria-controls="panel-portal">Portal Login</button>@endcanany
                <button type="button" role="tab" class="tab" id="tab-activity" data-tab="activity" aria-controls="panel-activity">Activity</button>
            </div>

            <section id="panel-addresses" role="tabpanel" aria-labelledby="tab-addresses">
                <x-card title="Addresses" :padded="false">
                    @can('update', $customer)
                        <x-slot:actions><x-button size="sm" variant="secondary" icon="plus" data-remote-modal="{{ route('customers.addresses.create', $customer) }}">Add Address</x-button></x-slot:actions>
                    @endcan
                    <div data-partial-url="{{ route('customers.addresses.index', $customer) }}">
                        @include('customers.addresses._list')
                    </div>
                </x-card>
            </section>

            <section id="panel-notes" role="tabpanel" aria-labelledby="tab-notes" hidden>
                <x-card title="Notes" :padded="false">
                    @can('addNote', $customer)
                        <form method="POST" action="{{ route('customers.notes.store', $customer) }}" data-ajax-form data-reset data-refresh-partials class="border-b border-line p-5">
                            @csrf
                            <x-form.textarea name="body" label="New note" rows="3" required />
                            <div class="mt-3 flex justify-end"><x-button type="submit" size="sm" icon="plus">Add Note</x-button></div>
                        </form>
                    @endcan
                    <div data-partial-url="{{ route('customers.notes.index', $customer) }}" data-lazy>
                        <div class="space-y-3 p-5"><div class="skeleton h-12 w-full"></div></div>
                    </div>
                </x-card>
            </section>

            @can('manageDocuments', $customer)
                <section id="panel-documents" role="tabpanel" aria-labelledby="tab-documents" hidden>
                    <x-card title="Documents" :padded="false">
                        <form method="POST" action="{{ route('customers.documents.store', $customer) }}" enctype="multipart/form-data" data-ajax-form data-reset data-refresh-partials class="grid gap-4 border-b border-line p-5 sm:grid-cols-[200px_1fr_auto] sm:items-start">
                            @csrf
                            <x-form.select name="category" id="document-category" label="Category" :options="App\Models\CustomerDocument::CATEGORIES" required />
                            <x-form.file name="file" label="File" required hint="PDF, image, Word or Excel, max {{ config('keyone.uploads.document_max_kb') / 1024 }} MB" />
                            <x-button type="submit" icon="upload" class="sm:mt-7">Upload</x-button>
                        </form>
                        <div data-partial-url="{{ route('customers.documents.index', $customer) }}" data-lazy>
                            <div class="space-y-3 p-5"><div class="skeleton h-12 w-full"></div></div>
                        </div>
                    </x-card>
                </section>
            @endcan

            @canany(['sales.sale.view', 'sales.booking.view'])
                <section id="panel-sales" role="tabpanel" aria-labelledby="tab-sales" hidden class="space-y-6">
                    @can('sales.sale.view')
                        <x-data-table :url="route('sales.table', ['customer' => $customer->public_id])" :search="false" sort="date" dir="desc" :columns="[
                            ['label' => 'Sale', 'sort' => 'date'], ['label' => 'Unit'], ['label' => 'Customer'], ['label' => 'Contract Value', 'class' => 'text-right', 'sort' => 'value'], ['label' => 'Status'],
                        ]" />
                    @endcan
                    @can('sales.booking.view')
                        <x-data-table :url="route('bookings.table', ['customer' => $customer->public_id])" :search="false" sort="date" dir="desc" :columns="[
                            ['label' => 'Booking', 'sort' => 'date'], ['label' => 'Unit'], ['label' => 'Customer'], ['label' => 'Agreed Price', 'class' => 'text-right', 'sort' => 'price'], ['label' => 'Booking Money', 'class' => 'text-right'], ['label' => 'Status'],
                        ]" />
                    @endcan
                </section>
            @endcanany
            @can('collections.payment.view')
                <section id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" hidden>
                    <x-data-table :url="route('customer-payments.table', ['customer' => $customer->public_id])" :search="false" sort="date" dir="desc" :columns="[
                        ['label' => 'Payment', 'sort' => 'date'], ['label' => 'Customer'], ['label' => 'For'], ['label' => 'Method'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
                    ]" />
                </section>
            @endcan

            @canany(['portal.links.manage', 'portal.grants.manage'])
                <section id="panel-portal" role="tabpanel" aria-labelledby="tab-portal" hidden>
                    <div data-partial-url="{{ route('portal-access.panel', ['customer', $customer->public_id]) }}" data-lazy><x-card><div class="skeleton h-14 w-full"></div></x-card></div>
                </section>
            @endcanany
            <section id="panel-activity" role="tabpanel" aria-labelledby="tab-activity" hidden>
                <x-card title="Activity" :padded="false">
                    <div data-partial-url="{{ route('customers.activity', $customer) }}" data-lazy>
                        <div class="space-y-3 p-5"><div class="skeleton h-12 w-full"></div></div>
                    </div>
                </x-card>
            </section>
        </div>
    </div>
</x-layouts.app>
