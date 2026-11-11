@php $row = 'flex items-center gap-3 border-b border-line px-5 py-3 last:border-0'; @endphp
<x-layouts.portal title="Documents" portal="investor">
    <x-page-header title="Documents" :breadcrumbs="['Home' => route('investor.dashboard'), 'Documents' => null]" />
    <div class="grid gap-6 xl:grid-cols-2">
        <x-card title="Agreements" :padded="false">
            @forelse ($agreements as $a)
                <div class="{{ $row }}">
                    <span class="icon-tile icon-tile-sm tone-accent"><x-icon name="file" size="h-4 w-4" /></span>
                    <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">Investment Agreement · Unit {{ $a->flat->flat_no }}</span><span class="block text-[13px] text-ink-500">{{ $a->project->name ?? $a->project->code }} · {{ $a->agreement_no }} · {{ App\Models\InvestorFlatAgreement::STATUSES[$a->status] }}</span></span>
                    <a href="{{ route('investor.documents.agreement', $a->public_id) }}?format=pdf" class="btn btn-secondary btn-sm"><x-icon name="download" size="h-4 w-4" />Download</a>
                </div>
            @empty
                <x-empty-state icon="file" title="No agreements" />
            @endforelse
        </x-card>
        <x-card title="Files Shared with You" :padded="false">
            @forelse ($files as $f)
                <div class="{{ $row }}">
                    <span class="icon-tile icon-tile-sm tone-info"><x-icon name="file" size="h-4 w-4" /></span>
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-ink-900">{{ $f->original_name }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::bytes($f->file_size) }} · {{ App\Support\Format::date($f->created_at) }}</span></span>
                    <a href="{{ route('investor.documents.file', $f->public_id) }}" class="btn btn-secondary btn-sm"><x-icon name="download" size="h-4 w-4" />Download</a>
                </div>
            @empty
                <x-empty-state icon="file" title="No shared files" />
            @endforelse
        </x-card>
        <x-card title="Receipts for Money You Invested" :padded="false">
            @forelse ($capital as $t)
                <div class="{{ $row }}">
                    <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">{{ $t->transaction_type === 'contribution' ? 'You Invested' : 'Capital Returned to You' }} · {{ App\Support\Format::money($t->amount) }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($t->transaction_date) }} · {{ $t->transaction_no }}</span></span>
                    <a href="{{ route('investor.documents.capital', $t->public_id) }}?format=pdf" class="btn btn-secondary btn-sm"><x-icon name="download" size="h-4 w-4" />Download</a>
                </div>
            @empty
                <x-empty-state icon="tag" title="No receipts" />
            @endforelse
        </x-card>
        <x-card title="Vouchers for Profit Paid to You" :padded="false">
            @forelse ($payouts as $p)
                <div class="{{ $row }}">
                    <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">Profit Paid to You · {{ App\Support\Format::money($p->amount) }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($p->paid_at) }} · {{ $p->payout_no }}@if ($p->status === 'reversed') · Cancelled @endif</span></span>
                    <a href="{{ route('investor.documents.payout', $p->public_id) }}?format=pdf" class="btn btn-secondary btn-sm"><x-icon name="download" size="h-4 w-4" />Download</a>
                </div>
            @empty
                <x-empty-state icon="check-square" title="No payouts" />
            @endforelse
        </x-card>
    </div>
</x-layouts.portal>
