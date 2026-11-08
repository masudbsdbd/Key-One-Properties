# Phase 2 UAT checklist

Use two accounts for every approval: the person who records a document cannot approve it.

## Construction

- [ ] Add work packages and milestones to a project; edit and reorder them.
- [ ] Submit a progress update with photos; approve it; an investor-visible update shows as published.
- [ ] Record labour; approve it; the project Cost tab shows it under Labour.

## Materials and warehouses

- [ ] Receive opening stock into a warehouse; the average cost is the weighted average of receipts.
- [ ] Issue stock to the project; the Cost tab shows material cost; stock cannot go below zero.
- [ ] Request an adjustment; a second user approves it; the movement ledger shows it.
- [ ] Borrow → approve → issue → return part → convert part; outstanding quantities and costs are correct.
- [ ] Preview a transfer, approve, post, then reverse it; balances return to where they were.

## Purchasing

- [ ] Create a supplier with an opening payable; the ledger shows it.
- [ ] Create a PO with discount and tax; submit; a second user approves.
- [ ] Receive part of the PO; status is Partially Received; stock increases at landed cost.
- [ ] Return part of the receipt before billing; supplier balance does not change.
- [ ] Bill the received lines plus a transport charge; approve; payable increases.
- [ ] Pay with allocation to the bill; over-allocation is refused; the bill becomes Paid.
- [ ] Supplier Ledger tab: running balance and CSV match; aging buckets add up to the payable.

## Contractors

- [ ] Create a contractor and a work order with 5% retention; approve it.
- [ ] Pay an advance; record measurements; a measurement above the contract value is refused.
- [ ] Bill the approved measurements with the advance adjusted; net payable = gross − advance − retention − other.
- [ ] Pay the bill; settle the work order; approve the retention-release bill; the work order is Completed.
- [ ] Contractor ledger and CSV show bill, advance, payment and retention release.

## Expenses

- [ ] A general expense has no project and never appears in project cost.
- [ ] A project expense needs a project; a general-only category is refused for it.
- [ ] Submit, reject with a reason, edit, resubmit, approve, pay; the history shows every step.
- [ ] Filter by project, category, status and dates; totals and CSV follow the filters.

## Integration and security

- [ ] Project page shows Cost, Materials, Purchases, Contractors and Expenses tabs only to users with those permissions.
- [ ] Dashboard "Awaiting You" lists only documents the user may approve.
- [ ] A user outside a project gets "forbidden" on its orders, work orders and expenses, even with a copied link.
- [ ] Hovering an icon button shows its label; hovering a disabled button shows why it is disabled.
- [ ] `php artisan keyone:reconcile` reports no differences.

Sign-off: ____________________   Date: ____________
