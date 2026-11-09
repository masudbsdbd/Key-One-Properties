<?php

namespace App\Modules\Reports\Reports;

use App\Models\CustomerPayment;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CollectionReceiptsReport extends BaseReport
{
    public function key(): string
    {
        return 'collection-receipts';
    }

    public function name(): string
    {
        return 'Collection Receipts';
    }

    public function group(): string
    {
        return 'collections';
    }

    public function basis(): string
    {
        return 'Posted payments by payment date; reversed payments listed and excluded from the total';
    }

    public function filters(): array
    {
        return ['project', 'period', 'status'];
    }

    public function statuses(): array
    {
        return ['posted' => 'Posted', 'reversed' => 'Reversed'];
    }

    public function search(): array
    {
        return ['payment_no', 'reference_no'];
    }

    public function columns(): array
    {
        return ['Date' => 'date', 'Payment' => 'text', 'Customer' => 'text', 'Project' => 'text', 'Unit' => 'text', 'For' => 'text', 'Method' => 'text', 'Amount' => 'money', 'Status' => 'text'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = CustomerPayment::query()->visibleTo($user)->whereIn('status', ['posted', 'reversed'])
            ->with(['customer:id,full_name', 'project:id,code', 'sale:id,flat_id', 'sale.flat:id,flat_no', 'booking:id,flat_id', 'booking.flat:id,flat_no']);
        if ($project = self::project($request, $user)) {
            $query->where('project_id', $project->id);
        }
        if (array_key_exists($s = (string) $request->input('status'), $this->statuses())) {
            $query->where('status', $s);
        }

        return $this->inPeriod($query, $request, 'payment_date')->orderBy('payment_date')->orderBy('id');
    }

    public function row(mixed $p): array
    {
        return [$p->payment_date, $p->payment_no, $p->customer->full_name, $p->project->code, ($p->sale ?? $p->booking)?->flat?->flat_no, $p->sale_id ? 'Sale' : 'Booking',
            CustomerPayment::METHODS[$p->method] ?? $p->method, $p->amount, $p->status === 'posted' ? 'Posted' : 'Reversed'];
    }

    public function totals(Builder $query): ?array
    {
        return ['Collected', null, null, null, null, null, null, Money::amount((string) (clone $query)->where('status', 'posted')->sum('amount')), null];
    }
}
