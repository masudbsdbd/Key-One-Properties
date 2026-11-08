<?php

namespace App\Support;

use App\Models\ConstructionProgressUpdate;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\ContractorPayment;
use App\Models\ContractorWorkOrder;
use App\Models\Expense;
use App\Models\LabourEntry;
use App\Models\MaterialBorrow;
use App\Models\MaterialTransfer;
use App\Models\PurchaseOrder;
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
            ['purchases.payment.approve', 'Supplier payments', 'tag', 'supplier-payments.index', fn () => SupplierPayment::query()->where('status', 'pending')],
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
