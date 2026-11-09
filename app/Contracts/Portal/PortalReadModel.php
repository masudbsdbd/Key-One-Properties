<?php

namespace App\Contracts\Portal;

use App\Models\Customer;
use App\Models\CustomerStatementEntry;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\Investor;
use App\Models\InvestorFlatAgreement;
use App\Models\InvestorLedgerEntry;
use App\Models\InvestorProfitAllocation;
use App\Modules\Collections\Services\CustomerStatement;
use App\Modules\Investors\Services\InvestorLedger;
use App\Modules\Portal\Services\CustomerPortal;
use App\Support\Money;

/**
 * Read-only contract for the Phase 4 customer and investor portals. Every query is scoped to the one
 * customer or investor passed in; the portal resolves that record from the signed-in portal user and
 * never from request input. No internal ids, cost worksheets or provisional profit leave this class.
 */
class PortalReadModel
{
    public function customer(Customer $customer): CustomerPortalView
    {
        $sales = FlatSale::query()->where('customer_id', $customer->id)->whereIn('status', FlatSale::ACTIVE)->with(['flat', 'project.stages'])->get();
        $bookings = FlatBooking::query()->where('customer_id', $customer->id)->where('status', 'confirmed')->whereDoesntHave('sale')->with(['flat', 'project.stages'])->get();
        $units = [];
        $portal = new CustomerPortal($customer);
        foreach ($sales as $sale) {
            $pos = $portal->position($sale);
            $items = $pos['plan']?->items->where('status', '!=', 'rescheduled')->sortBy('due_date')->values() ?? collect();
            $units[] = new CustomerUnit($sale->sale_no, 'sale', FlatSale::STATUSES[$sale->status], $sale->project->name, $sale->flat->flat_no,
                $pos['contract'], $pos['paid'], $pos['balance'], $pos['next']?->due_date->toDateString(), $pos['next'] ? Money::amount($pos['next']->outstanding()) : null,
                $sale->project->overallProgress(),
                $items->map(fn ($i) => ['sequence' => $i->sequence_no, 'due_date' => $i->due_date->toDateString(), 'amount' => Money::amount(Money::add((string) $i->principal_due, (string) $i->fee_due)),
                    'paid' => Money::amount(Money::add((string) $i->principal_paid, (string) $i->fee_paid)), 'status' => $i->status])->all());
        }
        foreach ($bookings as $b) {
            $pos = $portal->position($b);
            $units[] = new CustomerUnit($b->booking_no, 'booking', FlatBooking::STATUSES[$b->status], $b->project->name, $b->flat->flat_no,
                $pos['contract'], $pos['paid'], Money::amount(Money::sub((string) $b->booking_amount_expected, $pos['paid'])), null, null, $b->project->overallProgress(), []);
        }

        $running = '0';
        $lines = CustomerStatementEntry::query()->where('customer_id', $customer->id)->orderBy('entry_date')->orderBy('id')->get()->map(function ($e) use (&$running) {
            $running = Money::add($running, (string) $e->charge_amount, Money::neg((string) $e->credit_amount));

            return new StatementLine($e->entry_date->toDateString(), CustomerStatementEntry::TYPES[$e->entry_type] ?? $e->entry_type, $e->description,
                Money::amount((string) $e->charge_amount), Money::amount((string) $e->credit_amount), Money::amount($running));
        })->all();

        return new CustomerPortalView($customer->customer_code, $customer->full_name, CustomerStatement::balance($customer), $units, $lines);
    }

    public function investor(Investor $investor): InvestorPortalView
    {
        $agreements = InvestorFlatAgreement::query()->where('investor_id', $investor->id)->whereIn('status', ['active', 'closed'])->with(['flat', 'project.stages'])->orderBy('effective_from')->get();
        $holdings = [];
        foreach ($agreements->groupBy('flat_id') as $flatId => $versions) {
            $current = $versions->sortByDesc('version')->first();
            $b = InvestorLedger::balances($investor, $flatId);
            $profit = InvestorProfitAllocation::query()->where('investor_id', $investor->id)->where('flat_id', $flatId)->where('status', 'approved')
                ->with('calculation:id,calculation_no,net_profit_amount,approved_at')->orderBy('approved_at')->get()
                ->map(fn ($a) => ['reference' => $a->calculation->calculation_no, 'approved_at' => $a->approved_at->toDateString(), 'net_profit' => Money::amount((string) $a->calculation->net_profit_amount),
                    'share_percent' => Money::rate((string) $a->share_percent_snapshot), 'profit' => Money::amount((string) $a->profit_amount)])->all();
            $holdings[] = new InvestorHolding($current->agreement_no, InvestorFlatAgreement::STATUSES[$current->status], $current->project->name, $current->flat->flat_no,
                Money::rate((string) $current->profit_share_percent), $current->effective_from->toDateString(), $current->effective_to?->toDateString(),
                Money::amount((string) $current->committed_capital), $b['capital'], $b['payable'], $b['paid'], $b['profit_due'], $current->project->overallProgress(), $profit);
        }

        $kinds = InvestorLedgerEntry::query()->where('investor_id', $investor->id)->pluck('entry_kind', 'id');
        $capital = '0';
        $profit = '0';
        $ledger = InvestorLedgerEntry::query()->where('investor_id', $investor->id)->orderBy('entry_date')->orderBy('id')->get()->map(function ($e) use ($kinds, &$capital, &$profit) {
            $kind = $e->entry_kind === 'reversal' ? ($kinds[$e->reversal_of_id] ?? 'reversal') : $e->entry_kind;
            $in = in_array($kind, ['capital_in', 'profit_payable'], true);
            $amount = (string) $e->amount;
            if (str_starts_with($kind, 'capital')) {
                $capital = Money::add($capital, $in ? $amount : Money::neg($amount));
            } else {
                $profit = Money::add($profit, $in ? $amount : Money::neg($amount));
            }
            [$debit, $credit] = $in ? [$amount, '0'] : ['0', $amount];

            return new StatementLine($e->entry_date->toDateString(), InvestorLedgerEntry::KINDS[$e->entry_kind] ?? $e->entry_kind, $e->description,
                Money::amount($debit), Money::amount($credit), Money::amount(Money::add($capital, $profit)));
        })->all();
        $totals = InvestorLedger::balances($investor);

        return new InvestorPortalView($investor->investor_code, $investor->name, $totals['capital'], $totals['profit_due'], $holdings, $ledger);
    }
}
