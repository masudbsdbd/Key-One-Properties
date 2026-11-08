<?php

namespace App\Support;

use App\Models\ConstructionProgressUpdate;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\ContractorPayment;
use App\Models\ContractorWorkOrder;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatCostAllocationRun;
use App\Models\FlatPriceRevision;
use App\Models\FlatProfitCalculation;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentReschedule;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorFlatAgreement;
use App\Models\InvestorProfitDistribution;
use App\Models\LabourEntry;
use App\Models\MaterialBorrow;
use App\Models\MaterialTransfer;
use App\Models\PurchaseOrder;
use App\Models\SalesCancellation;
use App\Models\StockAdjustment;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Documents waiting for the user's approval, scoped by permission and project access.
 */
class ApprovalQueue
{
    /**
     * @return array<int, array{label: string, count: int, href: ?string, icon: string}>
     */
    public function for(User $user): array
    {
        $items = [
            ['purchases.approve', 'Purchase orders', 'note', 'purchase-orders.index', fn () => PurchaseOrder::query()->visibleTo($user)->where('status', 'submitted')],
            ['purchases.bill.approve', 'Supplier bills', 'file', 'supplier-bills.index', fn () => SupplierBill::query()->visibleTo($user)->where('status', 'draft')],
            ['purchases.payment.approve', 'Supplier payments', 'tag', 'supplier-payments.index', fn () => SupplierPayment::query()->visibleTo($user)->where('status', 'pending')],
            ['contractors.workorder.approve', 'Work orders', 'check-square', 'work-orders.index', fn () => ContractorWorkOrder::query()->visibleTo($user)->where('status', 'draft')],
            ['contractors.measurement.approve', 'Measurements', 'note', 'work-orders.index', fn () => ContractorMeasurement::query()->where('status', 'draft')->whereHas('workOrder', fn ($q) => $q->visibleTo($user))],
            ['contractors.bill.approve', 'Contractor bills', 'file', 'contractor-bills.index', fn () => ContractorBill::query()->visibleTo($user)->where('status', 'draft')],
            ['contractors.payment.approve', 'Contractor payments', 'tag', 'contractor-payments.index', fn () => ContractorPayment::query()->visibleTo($user)->where('status', 'pending')],
            ['expenses.approve', 'Expenses', 'note', 'expenses.index', fn () => Expense::query()->visibleTo($user)->where('status', 'submitted')],
            ['expenses.pay', 'Expenses to pay', 'tag', 'expenses.index', fn () => Expense::query()->visibleTo($user)->where('status', 'approved'), 'approved'],
            ['materials.adjust.approve', 'Stock adjustments', 'layers', 'adjustments.index', fn () => StockAdjustment::query()->where('status', 'submitted')->whereHas('warehouse', fn ($q) => $q->visibleTo($user))],
            ['materials.borrow.approve', 'Borrow requests', 'refresh', 'borrows.index', fn () => MaterialBorrow::query()->where('status', 'submitted')->whereHas('sourceWarehouse', fn ($q) => $q->visibleTo($user))],
            ['materials.transfer.approve', 'Transfers', 'arrow-right', 'transfers.index', fn () => MaterialTransfer::query()->where('status', 'submitted')->whereHas('sourceWarehouse', fn ($q) => $q->visibleTo($user))],
            ['construction.labour.approve', 'Labour entries', 'users', 'labour.index', fn () => LabourEntry::query()->where('status', 'submitted')->whereHas('project', fn ($q) => $q->visibleTo($user))],
            ['construction.progress.approve', 'Progress updates', 'activity', 'construction.index', fn () => ConstructionProgressUpdate::query()->where('status', 'submitted')->whereHas('project', fn ($q) => $q->visibleTo($user))],
            ['sales.booking.approve', 'Bookings', 'calendar-check', 'bookings.index', fn () => FlatBooking::query()->visibleTo($user)->where('status', 'pending'), 'pending'],
            ['sales.sale.approve', 'Sales', 'door', 'sales.index', fn () => FlatSale::query()->visibleTo($user)->where('status', 'pending'), 'pending'],
            ['sales.booking.cancel.approve', 'Cancellations', 'x', 'cancellations.index', fn () => SalesCancellation::query()->where('status', 'requested')
                ->where(fn ($q) => $q->whereIn('sale_id', FlatSale::query()->visibleTo($user)->select('id'))->orWhereIn('booking_id', FlatBooking::query()->visibleTo($user)->select('id'))), 'requested'],
            ['sales.price.change.approve', 'Price changes', 'tag', 'price-revisions.index', fn () => FlatPriceRevision::query()->where('status', 'pending')->whereIn('flat_id', Flat::query()->visibleTo($user)->select('flats.id')), 'pending'],
            ['sales.refund.approve', 'Refunds', 'restore', 'refunds.index', fn () => CustomerRefund::query()->visibleTo($user)->where('status', 'pending'), 'pending'],
            ['sales.refund.pay', 'Refunds to pay', 'tag', 'refunds.index', fn () => CustomerRefund::query()->visibleTo($user)->where('status', 'approved'), 'approved'],
            ['collections.plan.approve', 'Installment plans', 'calendar', 'installment-plans.index', fn () => InstallmentPlan::query()->visibleTo($user)->where('status', 'pending'), 'pending'],
            ['collections.plan.reschedule.approve', 'Reschedules', 'calendar', 'installment-plans.index', fn () => InstallmentReschedule::query()->where('status', 'requested')->whereIn('plan_id', InstallmentPlan::query()->visibleTo($user)->select('id'))],
            ['collections.payment.approve', 'Customer payments', 'tag', 'customer-payments.index', fn () => CustomerPayment::query()->visibleTo($user)->where('status', 'pending'), 'pending'],
            ['investor.agreement.approve', 'Investment agreements', 'file', 'investor-agreements.index', fn () => InvestorFlatAgreement::query()->visibleTo($user)->where('status', 'pending'), 'pending'],
            ['investor.capital.approve', 'Investor capital', 'tag', 'investor-capital.index', fn () => InvestorCapitalTransaction::query()->visibleTo($user)->where('status', 'pending'), 'pending'],
            ['investor.cost.approve', 'Cost allocation runs', 'layers', 'cost-runs.index', fn () => FlatCostAllocationRun::query()->visibleTo($user)->where('status', 'draft'), 'draft'],
            ['investor.profit.approve', 'Flat profit', 'bar-chart', 'profit-calculations.index', fn () => FlatProfitCalculation::query()->visibleTo($user)->where('status', 'submitted'), 'submitted'],
            ['investor.profit.distribution.approve', 'Profit distributions', 'check-square', 'distributions.index', fn () => InvestorProfitDistribution::query()->visibleTo($user)->where('status', 'pending_approval')],
            ['investor.profit.payout.post', 'Profit payouts', 'tag', 'distributions.index', fn () => InvestorProfitDistribution::query()->visibleTo($user)->where('status', 'approved')],
        ];

        $queue = [];
        foreach ($items as $item) {
            [$permission, $label, $icon, $route, $query] = $item;
            if (! $user->can($permission) || ! Route::has($route)) {
                continue;
            }
            $count = $query()->count();
            if ($count > 0) {
                $status = $item[5] ?? null;
                $queue[] = ['label' => $label, 'count' => $count, 'icon' => $icon, 'href' => route($route, $status ? ['status' => $status] : [])];
            }
        }

        return $queue;
    }
}
