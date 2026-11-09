@php $row = 'flex items-center gap-3 border-b border-line px-5 py-3 last:border-0'; @endphp
<x-layouts.portal title="Documents" portal="investor">
    <x-page-header title="Documents" :breadcrumbs="['Overview' => route('investor.dashboard'), 'Documents' => null]" />
    <div class="grid gap-6 xl:grid-cols-2">
        <x-card title="Agreements" :padded="false">
            @forelse ($agreements as $a)
                <div class="{{ $row }}">
                    <span class="icon-tile icon-tile-sm tone-accent"><x-icon name="file" size="h-4 w-4" /></span>
                    <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">{{ $a->agreement_no }} · v{{ $a->version }}</span><span class="block text-[13px] text-ink-500">{{ $a->project->code }} · Unit {{ $a->flat->flat_no }} · {{ App\Models\InvestorFlatAgreement::STATUSES[$a->status] }}</span></span>
                    <x-document-menu :url="route('investor.documents.agreement', $a->public_id)" compact />
                </div>
            @empty
                <x-empty-state icon="file" title="No agreements" />
            @endforelse
        </x-card>
        <x-card title="Shared Files" :padded="false">
            @forelse ($files as $f)
                <div class="{{ $row }}">
                    <span class="icon-tile icon-tile-sm tone-info"><x-icon name="file" size="h-4 w-4" /></span>
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-ink-900">{{ $f->original_name }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::bytes($f->file_size) }} · {{ App\Support\Format::date($f->created_at) }}</span></span>
                    <a href="{{ route('investor.documents.file', $f->public_id) }}" class="btn btn-ghost btn-icon" aria-label="Download {{ $f->original_name }}"><x-icon name="download" size="h-4 w-4" /></a>
                </div>
            @empty
                <x-empty-state icon="file" title="No shared files" />
            @endforelse
        </x-card>
        <x-card title="Capital Receipts" :padded="false">
            @forelse ($capital as $t)
                <div class="{{ $row }}">
                    <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">{{ $t->transaction_no }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($t->transaction_date) }} · {{ App\Models\InvestorCapitalTransaction::TYPES[$t->transaction_type] }}</span></span>
                    <x-money :value="$t->amount" class="text-sm font-medium" />
                    <x-document-menu :url="route('investor.documents.capital', $t->public_id)" compact />
                </div>
            @empty
                <x-empty-state icon="tag" title="No receipts" />
            @endforelse
        </x-card>
        <x-card title="Payout Vouchers" :padded="false">
            @forelse ($payouts as $p)
                <div class="{{ $row }}">
                    <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">{{ $p->payout_no }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($p->paid_at) }} · {{ App\Models\InvestorPayout::STATUSES[$p->status] }}</span></span>
                    <x-money :value="$p->amount" class="text-sm font-medium" />
                    <x-document-menu :url="route('investor.documents.payout', $p->public_id)" compact />
                </div>
            @empty
                <x-empty-state icon="check-square" title="No payouts" />
            @endforelse
        </x-card>
    </div>
</x-layouts.portal>
