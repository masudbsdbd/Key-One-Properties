<?php

namespace App\Support;

use App\Models\ContractorLedgerEntry;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Expense;
use App\Models\Flat;
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

        return $cards;
    }
}
