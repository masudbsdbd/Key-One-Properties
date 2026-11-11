<x-layouts.app title="Months & Years">
    <x-page-header title="Months & Years" :breadcrumbs="['Dashboard' => route('dashboard'), 'Accounting' => route('accounting.overview'), 'Months & Years' => null]" />
    <div data-partial-url="{{ route('accounting.periods.list') }}" data-lazy><x-card><div class="skeleton h-12 w-full"></div></x-card></div>
</x-layouts.app>
