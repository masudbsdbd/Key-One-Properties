<x-card :padded="false">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
        <div>
            <h2 class="text-[15px] font-semibold text-ink-900">{{ $title }}@isset($account) @if ($account) · {{ $account->label() }}@endif @endisset</h2>
            <p class="text-[13px] text-ink-500">{{ ($filters['asOf'] ?? false) ? 'As of '.Carbon\Carbon::parse($range['to'])->format('j M Y') : $range['label'] }}@if ($project ?? null) · {{ $project->code }} · {{ $project->name }}@endif</p>
        </div>
        @if ($canExport && ($report !== 'ledger' || ($account ?? null)))<x-export-menu :url="$exportUrl" :table="false" />@endif
    </header>
    @include('accounting.reports._'.$report)
</x-card>
