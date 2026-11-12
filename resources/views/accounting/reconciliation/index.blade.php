<x-layouts.app title="Reconciliation">
    <x-page-header title="Reconciliation" :breadcrumbs="['Dashboard' => route('dashboard'), 'Accounting' => route('accounting.overview'), 'Reconciliation' => null]">
        @can('accounting.reconciliation.run')
            <x-slot:actions><x-button icon="check" data-action="post" data-url="{{ route('accounting.reconciliation.post-all') }}" :disabled="! $enabled" :data-disabled-reason="$enabled ? null : 'Automatic posting is off'">Post All Waiting</x-button></x-slot:actions>
        @endcan
    </x-page-header>

    @unless ($enabled)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-card border border-warning-500/30 bg-warning-50 px-5 py-4 text-sm text-warning-600">
            <span class="font-medium">Automatic posting is off</span>
            @can('accounting.settings.manage')<x-button size="sm" variant="secondary" :href="route('accounting.settings.edit')">Accounting Settings</x-button>@endcan
        </div>
    @endunless

    <h2 class="mb-3 text-[15px] font-semibold text-ink-900">Records and Ledger @if ($start)<span class="font-normal text-ink-500">· books from {{ App\Support\Format::date($start) }}</span>@endif</h2>
    <div data-partial-url="{{ route('accounting.reconciliation.checks') }}" data-lazy><x-card><div class="skeleton h-12 w-full"></div></x-card></div>

    <h2 class="mb-3 mt-8 text-[15px] font-semibold text-ink-900">Waiting to Be Posted</h2>
    <x-data-table :url="route('accounting.reconciliation.table')" sort="date" dir="asc" search="Search type or reason" :columns="[
        ['label' => 'Date', 'sort' => 'date'], ['label' => 'Transaction'], ['label' => 'Amount', 'class' => 'text-right'], ['label' => 'Why It Waits'], ['label' => '', 'class' => 'w-44'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-44" data-table-filter aria-label="Status"><option value="">Waiting and failed</option><option value="pending">Waiting</option><option value="failed">Failed</option></select>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
