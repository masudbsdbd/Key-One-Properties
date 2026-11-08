<?php

namespace App\Support;

use App\Models\ContractorLedgerEntry;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Department;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\InvestorLedgerEntry;
use App\Models\Project;
use App\Models\StockBalance;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Real counts only; each module adds its own card when it exists and the user may see it.
 */
class DashboardStats
{
    /**
     * @return array<int, array{label: string, value: string, icon: string, href: ?string}>
     */
    public function for(User $user): array
    {
        $cards = [];

        if ($user->canAny(['projects.view', 'projects.view_all'])) {
            $projects = Project::query()->visibleTo($user);
            $cards[] = ['label' => 'Projects', 'value' => number_format((clone $projects)->count()), 'icon' => 'building', 'href' => route('projects.index')];
            $cards[] = ['label' => 'Active Projects', 'value' => number_format((clone $projects)->where('status', 'active')->count()), 'icon' => 'activity', 'href' => route('projects.index')];
        }

        if ($user->can('flats.view')) {
            $flats = Flat::query()->visibleTo($user);
            $cards[] = ['label' => 'Units', 'value' => number_format((clone $flats)->count()), 'icon' => 'door', 'href' => route('flats.index')];
            $cards[] = ['label' => 'Available Units', 'value' => number_format((clone $flats)->where('status', 'available')->count()), 'icon' => 'check-square', 'href' => route('flats.index')];
        }

        if ($user->canAny(['customers.view', 'customers.view_all'])) {
            $cards[] = ['label' => 'Customers', 'value' => number_format(Customer::query()->visibleTo($user)->count()), 'icon' => 'users', 'href' => route('customers.index')];
        }

        if ($user->can('users.view')) {
            $cards[] = ['label' => 'Active Users', 'value' => number_format(User::query()->active()->count()), 'icon' => 'users', 'href' => Route::has('users.index') ? route('users.index') : null];
        }

        if ($user->can('departments.view')) {
            $cards[] = ['label' => 'Departments', 'value' => number_format(Department::query()->count()), 'icon' => 'briefcase', 'href' => Route::has('departments.index') ? route('departments.index') : null];
        }

        if ($user->can('suppliers.ledger.view')) {
            $row = SupplierLedgerEntry::query()->selectRaw('COALESCE(SUM(charge_amount - payment_amount),0) AS b')->toBase()->first();
            $cards[] = ['label' => 'Supplier Payable', 'value' => Format::money($row->b, false), 'icon' => 'briefcase', 'href' => route('suppliers.index')];
        }

        if ($user->can('contractors.ledger.view')) {
            $row = ContractorLedgerEntry::query()->selectRaw('COALESCE(SUM(charge_amount - payment_amount),0) AS b')->toBase()->first();
            $cards[] = ['label' => 'Contractor Payable', 'value' => Format::money($row->b, false), 'icon' => 'user', 'href' => route('contractors.index')];
        }

        if ($user->canAny(['expenses.view.general', 'expenses.view.project'])) {
            $month = now(config('app.display_timezone'));
            $total = Expense::query()->visibleTo($user)->whereIn('status', ['approved', 'paid'])
                ->whereBetween('expense_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])->sum('amount');
            $cards[] = ['label' => 'Expenses This Month', 'value' => Format::money((string) $total, false), 'icon' => 'note', 'href' => route('expenses.index')];
        }

        if ($user->can('materials.view')) {
            $low = StockBalance::query()->whereHas('warehouse', fn ($q) => $q->visibleTo($user))
                ->whereHas('material', fn ($q) => $q->where('reorder_level', '>', 0)->whereColumn('materials.reorder_level', '>', 'stock_balances.owned_qty'))->count();
            $cards[] = ['label' => 'Low Stock Items', 'value' => number_format($low), 'icon' => 'layers', 'href' => route('stock.index')];
        }

        if ($user->can('sales.sale.view') && Route::has('sales.index')) {
            $cards[] = ['label' => 'Units Sold', 'value' => number_format(FlatSale::query()->visibleTo($user)->whereIn('status', FlatSale::ACTIVE)->count()), 'icon' => 'door', 'href' => route('sales.index')];
        }

        if ($user->can('collections.payment.view') && Route::has('collections.overview')) {
            $today = now(config('app.display_timezone'));
            $collected = CustomerPayment::query()->visibleTo($user)->where('status', 'posted')->whereBetween('payment_date', [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()])->sum('amount');
            $cards[] = ['label' => 'Collected This Month', 'value' => Format::money((string) $collected, false), 'icon' => 'tag', 'href' => route('customer-payments.index')];
            $overdue = InstallmentScheduleItem::query()->whereIn('plan_id', InstallmentPlan::query()->visibleTo($user)->where('status', 'active')->select('id'))
                ->where('status', '!=', 'rescheduled')->whereDate('due_date', '<', $today->toDateString())
                ->selectRaw('COALESCE(SUM(principal_due + fee_due - principal_paid - fee_paid),0) AS b')->toBase()->first();
            $cards[] = ['label' => 'Overdue Installments', 'value' => Format::money((string) $overdue->b, false), 'icon' => 'alert', 'href' => route('dues.index')];
        }

        if ($user->can('investor.ledger.view') && Route::has('distributions.index')) {
            $row = InvestorLedgerEntry::query()->from('investor_ledger_entries as e')->leftJoin('investor_ledger_entries as o', 'o.id', '=', 'e.reversal_of_id')
                ->whereIn('e.flat_id', Flat::query()->visibleTo($user)->select('flats.id'))
                ->selectRaw("COALESCE(SUM(CASE COALESCE(o.entry_kind, e.entry_kind) WHEN 'profit_payable' THEN e.amount WHEN 'profit_paid' THEN -e.amount ELSE 0 END),0) AS b")->toBase()->first();
            $cards[] = ['label' => 'Investor Profit Due', 'value' => Format::money((string) $row->b, false), 'icon' => 'bar-chart', 'href' => route('distributions.index')];
        }

        return $cards;
    }
}
