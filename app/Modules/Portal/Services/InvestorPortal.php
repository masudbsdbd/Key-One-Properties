<?php

namespace App\Modules\Portal\Services;

use App\Models\Attachment;
use App\Models\Flat;
use App\Models\FlatProfitCalculation;
use App\Models\Investor;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorFlatAgreement;
use App\Models\InvestorPayout;
use App\Models\InvestorProfitAllocation;
use App\Models\InvestorProfitDistribution;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Modules\Investors\Services\InvestorLedger;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everything the investor portal reads, always for the investor resolved from the signed-in account.
 * Profit is reported in four separate states: estimated (draft calculation), pending approval
 * (submitted), approved but unpaid, and paid. Nothing here returns other investors or internal ids.
 */
class InvestorPortal
{
    public function __construct(private PortalContext $context) {}

    public function investor(): Investor
    {
        return $this->context->investor;
    }

    /** Approved agreements (active or closed) of this investor. */
    public function agreements(): Builder
    {
        return InvestorFlatAgreement::query()->where('investor_id', $this->investor()->id)->whereIn('status', ['active', 'closed']);
    }

    public function agreement(string $publicId): InvestorFlatAgreement
    {
        return $this->agreements()->where('public_id', $publicId)->with(['flat.building', 'flat.floor', 'project'])->first() ?? abort(404);
    }

    /** A unit the investor holds through an approved agreement. */
    public function flat(string $publicId): Flat
    {
        $flat = Flat::query()->where('public_id', $publicId)->whereIn('id', PortalAccess::investorFlatIds($this->investor()))->with(['project', 'building', 'floor'])->first();

        return $flat ?? abort(404);
    }

    public function project(string $publicId): Project
    {
        return Project::query()->where('public_id', $publicId)->whereIn('id', PortalAccess::investorProjectIds($this->investor()))->first() ?? abort(404);
    }

    /** @return array{capital: string, committed: string, active: int, paid: string, approved_due: string, pending: string, estimated: string, distribution_approved: string} */
    public function summary(): array
    {
        $investor = $this->investor();
        $b = InvestorLedger::balances($investor);
        $proposed = fn (string $status) => Money::amount(Money::add('0', ...InvestorProfitAllocation::query()->where('investor_id', $investor->id)
            ->whereHas('calculation', fn ($q) => $q->where('status', $status))->pluck('profit_amount')->map(fn ($v) => (string) $v)->all()));
        $active = $this->agreements()->where('status', 'active')->get(['committed_capital']);

        return [
            'capital' => $b['capital'],
            'committed' => Money::amount(Money::add('0', ...$active->map(fn ($a) => (string) $a->committed_capital)->all())),
            'active' => $active->count(),
            'paid' => $b['paid'],
            'approved_due' => $b['profit_due'],
            'distribution_approved' => Money::amount((string) InvestorProfitDistribution::query()->where('investor_id', $investor->id)->where('status', 'approved')->sum('amount')),
            'pending' => $proposed('submitted'),
            'estimated' => $proposed('draft'),
        ];
    }

    /** Balances and profit states of one unit. */
    public function flatPosition(Flat $flat): array
    {
        $investor = $this->investor();
        $b = InvestorLedger::balances($investor, $flat->id);
        $latest = fn (array $statuses) => FlatProfitCalculation::query()->where('flat_id', $flat->id)->whereIn('status', $statuses)->latest('id')->first();
        $approved = $latest(['approved']);
        $open = $latest(['draft', 'submitted']);
        $mine = fn (?FlatProfitCalculation $c) => $c ? InvestorProfitAllocation::query()->where('profit_calculation_id', $c->id)->where('investor_id', $investor->id)->first() : null;

        return [
            'balances' => $b,
            'approved' => $approved, 'approved_share' => $mine($approved),
            'open' => $open, 'open_share' => $mine($open),
        ];
    }

    /** Cost categories this account may see for a unit or project; others are hidden, not zeroed. */
    public function visibleCostCategories(Flat|Project $resource): array
    {
        return collect(ProjectCostAllocation::CATEGORIES)
            ->filter(fn ($label, $key) => $this->context->allows('investor.portal.cost.'.$key.'.view', $resource))->all();
    }

    public function capitalTransactions(?Flat $flat = null): Collection
    {
        return InvestorCapitalTransaction::query()->where('investor_id', $this->investor()->id)->whereIn('status', ['pending', 'posted', 'reversed'])
            ->when($flat, fn ($q) => $q->where('flat_id', $flat->id))->with(['flat:id,flat_no', 'project:id,code'])
            ->latest('transaction_date')->latest('id')->get();
    }

    public function payouts(?Flat $flat = null): Collection
    {
        return InvestorPayout::query()->where('investor_id', $this->investor()->id)
            ->when($flat, fn ($q) => $q->whereHas('distribution', fn ($d) => $d->where('flat_id', $flat->id)))
            ->with('distribution:id,distribution_no,flat_id')->latest('paid_at')->latest('id')->get();
    }

    /** Agreement approval, capital, profit approvals, distributions and payouts of one unit, newest first. */
    public function timeline(InvestorFlatAgreement $agreement): Collection
    {
        $investor = $this->investor();
        $items = collect([['at' => $agreement->approved_at, 'title' => 'Agreement approved', 'meta' => $agreement->agreement_no.' · v'.$agreement->version, 'tone' => 'success']]);
        foreach ($this->capitalTransactions($agreement->flat) as $t) {
            if ($t->posted_at) {
                $items->push(['at' => $t->posted_at, 'title' => ($t->transaction_type === 'contribution' ? 'Capital received' : 'Capital returned'), 'meta' => $t->transaction_no.' · '.Format::money($t->amount), 'tone' => 'info']);
            }
            if ($t->reversed_at) {
                $items->push(['at' => $t->reversed_at, 'title' => 'Capital entry reversed', 'meta' => $t->transaction_no, 'tone' => 'danger']);
            }
        }
        InvestorProfitAllocation::query()->where('investor_id', $investor->id)->where('flat_id', $agreement->flat_id)->where('status', 'approved')
            ->with('calculation:id,calculation_no')->get()
            ->each(fn ($a) => $items->push(['at' => $a->approved_at, 'title' => 'Profit approved', 'meta' => $a->calculation->calculation_no.' · '.Format::money($a->profit_amount), 'tone' => 'success']));
        InvestorProfitDistribution::query()->where('investor_id', $investor->id)->where('flat_id', $agreement->flat_id)->whereNotNull('approved_at')->whereIn('status', ['approved', 'paid'])->get()
            ->each(fn ($d) => $items->push(['at' => $d->approved_at, 'title' => 'Distribution approved', 'meta' => $d->distribution_no.' · '.Format::money($d->amount), 'tone' => 'warning']));
        foreach ($this->payouts($agreement->flat) as $p) {
            $items->push(['at' => $p->created_at, 'title' => $p->status === 'reversed' ? 'Payout reversed' : 'Profit paid', 'meta' => $p->payout_no.' · '.Format::money($p->amount), 'tone' => $p->status === 'reversed' ? 'danger' : 'success']);
        }

        return $items->filter(fn ($i) => $i['at'])->sortByDesc('at')->values();
    }

    /** Files shared with the portal on this investor's agreements and capital entries. */
    public function sharedFiles(?InvestorFlatAgreement $agreement = null): Collection
    {
        $agreements = $agreement ? collect([$agreement->id]) : $this->agreements()->pluck('id');
        $capital = InvestorCapitalTransaction::query()->where('investor_id', $this->investor()->id)->whereIn('agreement_id', $agreements)->pluck('id');

        return Attachment::query()->where('portal_visible', true)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('attachable_type', (new InvestorFlatAgreement)->getMorphClass())->whereIn('attachable_id', $agreements))
                ->orWhere(fn ($w) => $w->where('attachable_type', (new InvestorCapitalTransaction)->getMorphClass())->whereIn('attachable_id', $capital)))
            ->latest()->get();
    }

    public function sharedFile(string $publicId): Attachment
    {
        return $this->sharedFiles()->firstWhere('public_id', $publicId) ?? abort(404);
    }
}
