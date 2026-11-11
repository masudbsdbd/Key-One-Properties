<?php

namespace App\Modules\Reports\Reports;

use App\Models\ContractorPayment;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\FinancialEvent;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorPayout;
use App\Models\Project;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Modules\Investors\Controllers\CapitalController;
use App\Modules\Reports\BaseReport;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

/** Every receipt and payment by date, from the financial events the modules record when money moves. */
class DailyCashBookReport extends BaseReport
{
    public const IN = ['collection.received' => 'Customer Payment', 'investment.received' => 'Investor Capital', 'contractor.advance_refunded' => 'Advance Refunded by Contractor'];

    public const OUT = ['purchase.supplier_payment' => 'Supplier Payment', 'contractor.payment' => 'Contractor Payment', 'expense.paid' => 'Expense',
        'profit.distribution_paid' => 'Investor Profit', 'investment.withdrawn' => 'Capital Returned', 'collection.refunded' => 'Customer Refund'];

    public function key(): string
    {
        return 'daily-cash-book';
    }

    public function name(): string
    {
        return 'Daily Cash Book';
    }

    public function group(): string
    {
        return 'cash';
    }

    public function permission(): string
    {
        return 'dashboard.executive.view';
    }

    public function basis(): string
    {
        return 'Posted receipts and payments by payment date; a reversal shows as a negative line';
    }

    public function columns(): array
    {
        return ['Date' => 'date', 'Entry' => 'text', 'Document' => 'text', 'Party' => 'text', 'Project' => 'text', 'Method' => 'text', 'Money In' => 'money', 'Money Out' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = FinancialEvent::query()->whereIn('event_type', array_keys(self::IN + self::OUT))
            ->where(fn (Builder $q) => $q->whereNull('project_id')->orWhereIn('project_id', Project::query()->visibleTo($user)->select('projects.id')))
            ->with(['project:id,code', 'source' => fn (MorphTo $source) => $source->morphWith([
                CustomerPayment::class => ['customer:id,full_name'], CustomerRefund::class => ['customer:id,full_name'], SupplierPayment::class => ['supplier:id,name'],
                ContractorPayment::class => ['contractor:id,name'], InvestorPayout::class => ['investor:id,name'], InvestorCapitalTransaction::class => ['investor:id,name'],
            ])]);
        if ($project = self::project($request, $user)) {
            $query->where('project_id', $project->id);
        }

        return $this->inPeriod($query, $request, 'effective_at')->orderByDesc('effective_at')->orderByDesc('id');
    }

    public function row(mixed $e): array
    {
        $source = $e->source;
        $number = collect(['payment_no', 'expense_no', 'payout_no', 'transaction_no', 'refund_no'])->map(fn ($f) => $source?->{$f})->filter()->first() ?? ($e->payload['payment_no'] ?? null);
        $party = $source?->customer?->full_name ?? $source?->supplier?->name ?? $source?->contractor?->name ?? $source?->investor?->name ?? $source?->payee_name;
        $method = $source?->payment_method ?? ($e->payload['method'] ?? null);
        $in = array_key_exists($e->event_type, self::IN);

        return [$e->effective_at, (self::IN + self::OUT)[$e->event_type].($e->reversal_of_id ? ' (reversed)' : ''), $number, $party, $e->project?->code,
            CapitalController::METHODS[$method] ?? $method, $in ? $e->amount : null, $in ? null : $e->amount];
    }

    public function totals(Builder $query): ?array
    {
        $sum = fn (array $types) => (string) (clone $query)->reorder()->whereIn('event_type', array_keys($types))->sum('amount');
        [$in, $out] = [$sum(self::IN), $sum(self::OUT)];

        return ['Total · Net '.Format::money(Money::sub($in, $out)), null, null, null, null, null, Money::amount($in), Money::amount($out)];
    }
}
