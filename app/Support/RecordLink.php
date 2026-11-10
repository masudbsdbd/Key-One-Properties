<?php

namespace App\Support;

use App\Models\Contractor;
use App\Models\ContractorBill;
use App\Models\ContractorPayment;
use App\Models\ContractorWorkOrder;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatCostAllocationRun;
use App\Models\FlatProfitCalculation;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\Investor;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorFlatAgreement;
use App\Models\InvestorProfitDistribution;
use App\Models\LabourEntry;
use App\Models\MaterialBorrow;
use App\Models\MaterialTransfer;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\StockIssue;
use App\Models\StockIssueReturn;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The detail page of a record, but only when the signed-in user may open it, so references in lists and
 * detail pages link through instead of dead-ending in a 403. Project visibility is read once per request.
 * Eager loads must select public_id (and project_id for project-scoped records) or the reference stays plain.
 */
final class RecordLink
{
    /** class => [route, permission(s), visibility: project|record|gate:<ability>|null] */
    private const MAP = [
        Project::class => ['projects.show', null, 'self'],
        Flat::class => ['flats.show', 'flats.view', 'project'],
        Customer::class => ['customers.show', null, 'record'],
        Supplier::class => ['suppliers.show', 'suppliers.view', null],
        Contractor::class => ['contractors.show', 'contractors.view', null],
        Investor::class => ['investors.show', 'investor.view', null],
        FlatSale::class => ['sales.show', 'sales.sale.view', 'project'],
        FlatBooking::class => ['bookings.show', 'sales.booking.view', 'project'],
        CustomerRefund::class => ['refunds.show', 'sales.refund.view', 'project'],
        CustomerPayment::class => ['customer-payments.show', 'collections.payment.view', 'project'],
        InstallmentPlan::class => ['installment-plans.show', 'collections.plan.view', 'record'],
        PurchaseOrder::class => ['purchase-orders.show', 'purchases.view', 'record'],
        SupplierBill::class => ['supplier-bills.show', 'purchases.view', 'record'],
        SupplierPayment::class => ['supplier-payments.show', 'purchases.view', 'record'],
        ContractorWorkOrder::class => ['work-orders.show', null, 'gate:wo:view'],
        ContractorBill::class => ['contractor-bills.show', null, 'gate:cbill:view'],
        ContractorPayment::class => ['contractor-payments.show', null, 'gate:cpay:view'],
        Expense::class => ['expenses.show', null, 'record'],
        MaterialBorrow::class => ['borrows.show', 'materials.view', 'record'],
        MaterialTransfer::class => ['transfers.show', 'materials.view', 'record'],
        InvestorFlatAgreement::class => ['investor-agreements.show', 'investor.view', 'project'],
        InvestorCapitalTransaction::class => ['investor-capital.show', 'investor.view', 'project'],
        InvestorProfitDistribution::class => ['distributions.show', 'investor.view', 'record'],
        FlatProfitCalculation::class => ['profit-calculations.show', 'investor.cost.view', 'project'],
        FlatCostAllocationRun::class => ['cost-runs.show', 'investor.cost.view', 'project'],
    ];

    public static function url(?Model $record, ?User $user = null): ?string
    {
        $user ??= auth()->user();
        if ($user && $record instanceof LabourEntry) {
            return Gate::forUser($user)->allows('construction:view', $record->project) ? route('labour.index', ['search' => $record->entry_no]) : null;
        }
        if ($user && $record instanceof StockIssueReturn) {
            $record = $record->issue;
        }
        if ($user && $record instanceof StockIssue) {
            return $user->can('materials.view') && $record->warehouse?->isVisibleTo($user) ? route('stock.movements', ['issue' => $record->public_id]) : null;
        }
        if (! $record || ! $user || ! $record->exists || ! isset(self::MAP[$record::class]) || $record->getRouteKey() === null) {
            return null;
        }
        [$route, $permission, $visibility] = self::MAP[$record::class];
        if ($permission && ! $user->can($permission)) {
            return null;
        }
        $allowed = match (true) {
            $visibility === 'self' => self::projectVisible($user, $record->getKey()),
            $visibility === 'project' => self::projectVisible($user, $record->getAttribute('project_id')),
            $visibility === 'record' => $record->isVisibleTo($user),
            is_string($visibility) && str_starts_with($visibility, 'gate:') => Gate::forUser($user)->allows(substr($visibility, 5), $record),
            default => true,
        };

        return $allowed ? route($route, $record) : null;
    }

    private static function projectVisible(User $user, mixed $projectId): bool
    {
        if ($projectId === null) {
            return false;
        }
        $key = 'record-link.projects.'.$user->id;
        $attributes = request()->attributes;
        if (! $attributes->has($key)) {
            $attributes->set($key, $user->can('projects.view_all') ? true : Project::query()->visibleTo($user)->pluck('id')->flip()->all());
        }
        $visible = $attributes->get($key);

        return $visible === true || isset($visible[$projectId]);
    }
}
