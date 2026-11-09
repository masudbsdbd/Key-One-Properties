# Phase 3 decisions and plan

Spec: `KEY_ONE_PHASE_3_BLUEPRINT.md`. This file records how the blueprint maps onto the code that
exists after Phase 2, and the defaults chosen where the client has not decided yet.

## 1. Phase 1/2 compatibility audit

| Phase 3 needs | Already exists | Plan |
| --- | --- | --- |
| Flats, buildings, floors | `flats` (`saleable_area DECIMAL(12,2)`, `area_unit`, `list_price`, status incl. `booked` / `sold`, `MANUAL_STATUSES` keeps those two out of manual edits) | Reuse. Booked/sold are set only by the sales services. Area precision stays 12,2 (no destructive change) |
| Price history | `flat_price_history` (old, new, reason, changed_by, effective_at) written by `FlatService::changePrice` | Reuse as the history. Add `flat_price_revisions` (request → approve); approval calls `changePrice` and stamps `approved_by` on the history row (new nullable column) |
| Customers | `customers` with `public_id`, unique `primary_mobile`, visibility `customers.view_all` or own (`created_by`) | Reuse. Bookings may only use customers the user can see |
| Projects / access | `Project::visibleTo()` (membership or `projects.view_all`) | Every Phase 3 query scopes by the flat's project |
| Financial events | `financial_events` outbox (idempotency key, `reversal_of_id`, type catalogue with Phase 3 placeholders) | Reuse; add missing types |
| Project cost | `project_cost_allocations` (material, labour, contractor, expense; project-level) | Add nullable `flat_id` so flat-direct costs are distinguishable; project expenses may name a flat |
| Files | Polymorphic `attachments` (private disk, registry with view/manage gates) | Agreements, registration documents, handover acknowledgements and investor agreements use it instead of `*_file_id` columns |
| Approval guard | `Approval::ensureNotSelf` / `ensureStatus` | Reused for every money approval |
| Document numbers | `DocumentNumber::next()` | BK, SL, AG, RF, CN, PL, CP, MR, IV, IA, IC, FCR, FPC, ID, IP |
| Exports / print | `Report` (CSV/PDF/print) and `Documents` | Receipts, statements, booking and sale documents use them |
| UI | Remote modal, data tables, Select2 remote, tooltips, disabled reasons, line items, timeline | Reused; forms open in the existing modal (the app has no drawer pattern in use) |
| Generic approvals | `approval_requests` (unused) | Not used; Phase 3 documents carry their own status like Phase 2 |

Folder and route names follow the repository, not the blueprint's `/erp` prefix:
`app/Modules/{Sales,Collections,Investors}`, `routes/{sales,collections,investors}.php`,
`resources/views/{sales,collections,investors}`, `tests/Feature/Phase3`.

## 2. Schema diff (proposed → built)

| Blueprint | Built as | Difference |
| --- | --- | --- |
| `flat_price_histories` | existing `flat_price_history` + new `flat_price_revisions` | requests and approvals are separate rows; history stays append-only |
| `flat_bookings` | `flat_bookings` | as proposed, plus `public_id` |
| (claim slot) | `active_flat_claims` (`flat_id` UNIQUE, `claimant_type`, `claimant_id`) | the database unique key decides concurrent bookings |
| `flat_sales` | `flat_sales` | as proposed, plus `public_id` |
| `sale_agreements` | `sale_agreements` | file through `attachments` |
| `booking_cancellations` | `sales_cancellations` | covers a booking **or** a sale (`booking_id` / `sale_id` nullable) |
| `customer_refunds` | `customer_refunds` | as proposed |
| `flat_registrations`, `flat_handovers` | same | files through `attachments` |
| `installment_plans`, `installment_schedule_items` | same | items add `kind` (down_payment / installment) and status `rescheduled` |
| `customer_payments`, `payment_allocations` | same | `purpose` (booking / sale) |
| `customer_late_fee_policies`, `late_fee_assessments` | same | — |
| `installment_reschedules` | same | — |
| `customer_statement_entries` | same | — |
| `money_receipts` | same | voided flag instead of deleting |
| `investors` | `investors` | no portal user is created |
| `investor_flat_agreements` | same | `investment_scope` column fixed to `flat` (extensibility point) |
| `investor_capital_transactions` | same | — |
| `flat_cost_allocation_runs`, `flat_cost_allocations` | same | one allocation row per flat per cost category per run (sources listed in the run snapshot) |
| `flat_profit_calculations`, `investor_profit_allocations`, `investor_profit_distributions`, `investor_payouts`, `investor_ledger_entries` | same | — |

All migrations are additive. No Phase 1/2 column is dropped or retyped.

## 3. Financial events and project cost integration

- Posted customer payment → `collection.received`; reversal → negative event linked by `reversal_of_id`.
- Refund paid → `collection.refunded`. Booking confirmed → `booking.created`; cancelled → `booking.cancelled`;
  sale approved → `sale.confirmed`; late fee assessed / waived → `late_fee.assessed` / `late_fee.waived`.
- Capital contribution / return → `investment.received` / `investment.withdrawn`.
- Profit payable approved → `profit.payable_approved`; distribution approved → `profit.distribution_approved`;
  payout → `profit.distribution_paid`.
- Flat cost: direct = `project_cost_allocations` rows with `flat_id`; shared pool = rows without `flat_id`.
  A cost-allocation run freezes flat areas and the pool, splits the pool by saleable area per category with
  largest-remainder rounding, and lists the source rows it used. A newer approved run supersedes the older
  one, so a source cost is counted once in the latest run and never added across runs.
- GRN, supplier bills and payments never enter flat cost; only Phase 2 cost rows do (stock issue, labour,
  contractor bill, project expense). This keeps the Phase 2 "no double counting" rule.

## 4. Permissions and concurrency

- Permission names as in the blueprint (`sales.*`, `collections.*`, `investor.*`). Admin role gets all.
- Every record is reached through its flat's project; out-of-scope records answer 403.
- Self-approval is blocked for booking approval, sale approval, refunds, payment posting, reschedules,
  capital transactions, cost runs, profit approval, distributions and payouts.
- Booking / sale claims: inside one transaction the flat row is locked (`lockForUpdate`) and a row is
  inserted in `active_flat_claims`; a duplicate key means another claim won → 409.
- Investor shares: the flat row is locked while summing active shares; > 100.0000 → 422.
- Payments, refunds, capital transactions, distributions and payouts carry idempotency keys.
- Schedule items, distributions and payable balances are locked when money is applied.

## 5. UI plan

Sidebar groups Sales, Collections and Investors (permission gated). Pages: sales overview and flat
availability board; bookings (list, wizard in the modal, detail tabs Overview / Agreement / Payments /
History); sales with registration and handover; refunds; price revisions. Collections dashboard; plans
with schedule builder and preview; payments with allocation preview; receipts; customer statement;
overdue aging; late-fee policies; reschedule comparison. Investor directory and profile tabs; agreement
editor with a live 100% gauge; capital transactions; cost runs and worksheet; profit calculations;
distributions and payouts; investor ledger. Every list exports CSV/PDF/print; receipts and statements print.

## 6. Policy decisions — defaults until the client decides

| Question (blueprint §11) | Default built | Where to change |
| --- | --- | --- |
| Revenue recognition / profit eligibility | **Not configured: profit calculations are provisional and approval is blocked.** Options offered: sale approved, registered, handed over, fully collected | Settings → Sales (`sales.revenue_recognition`) |
| Loss treatment | Loss gives zero distributable profit; investors are not charged | — |
| Cost pool | All project-level Phase 2 cost is shared by saleable area across all flats with area > 0; costs tagged with a flat are direct | Per run (area snapshot) |
| Cancellation / refund | Cancellation needs approval; the flat is released when the cancellation is approved; refund is a separate request with its own approval and payment, amount up to what the customer paid | — |
| Late fee | None unless a policy exists for the customer. Percent is applied to the unpaid principal of the overdue item; grace days; once or per month; late fees are not flat revenue | Customer late-fee policy |
| Payment application order | Oldest due date first; within an item, fee before principal; then future items; any rest is unapplied advance | — |
| Investment share changes | Agreements have effective dates; a calculation uses agreements active on its date; no time or capital proration | — |
| Payment methods | Cash, bank transfer, cheque, mobile banking (same as Phase 2) | — |

## 7. Investor agreements and capital (P5)

- Share limit: for any single date, the shares of active agreements on a unit add up to at most 100.0000%.
  The check takes the peak over the new agreement's date range (non-overlapping agreements do not add up)
  and runs on save, submit and approve; approval locks the unit row first.
- Versions: "Amend" copies an active agreement into a new draft (`version + 1`, `supersedes_id`). The old
  version stays active until the amendment is approved; it is then closed the day before the new start date.
- Capital is tracked per investor and unit across versions. Contributions (posted + pending) never exceed the
  current agreement's committed capital; returns never exceed posted capital less pending returns. Contributions
  need an active agreement; returns are also allowed on a closed one. Posting needs a second user.
- Reversing a contribution is refused while it would leave capital below zero (reverse the return first).
- Investor directory is company-wide (`investor.view`); agreements, capital and ledger rows follow project visibility.

## 8. Cost allocation and flat profit (P6)

- Direct unit cost comes from project expenses tagged with a unit (new optional Unit field on project
  expenses; the cost row carries `flat_id`). Material, labour and contractor cost stay shared.
- A run freezes `source_max_id`, the cutoff and the unit areas. It is stale when a cost row up to the cutoff
  is posted later or a unit area changes; stale runs cannot be approved, and profit calculated on a stale
  run cannot be approved either. Approving a run supersedes the previous one.
- Profit = sale net contract value − direct − shared − other cost (other cost needs a note). Investor amounts
  are rounded down to the paisa; the company share is the explicit remainder. A loss gives investors zero.
- Approval needs `investor.profit.approve`, a second user, both policy settings (Settings → Investor Profit
  Policy), eligible revenue, the latest approved run, unchanged agreements, and posts only the change
  against the payable already approved for that investor and unit (`profit_payable` in the investor ledger).
  An investor's new profit can never drop below what was already paid to them.

## 9. Distribution and payout (P7)

- Approved payable → distribution request (`investor.profit.distribution.create`) → approval by another user
  (`investor.profit.distribution.approve`) → payout (`investor.profit.payout.post`). Approval is not payment:
  only a posted payout writes `profit_paid` to the investor ledger.
- A request or approval can never exceed profit due less other open distributions; payouts re-check the due
  amount. Requests and payouts carry idempotency keys; the investor row is locked while amounts are checked.
- Reversing a payout (`investor.profit.reverse`) writes a reversal entry and a reversing financial event and
  returns the distribution to Approved so it can be paid again or rejected.

## 10. Implementation checklist

- [x] P1 UI shell: navigation, Phase 3 option sources, shared components
- [x] P2 Schema, models, permissions, claims, invariants
- [x] P3 Booking, sale, cancellation, refund, price revisions, registration, handover
- [x] P4 Installment plans, collections, allocation, late fees, reschedule, receipts, statement
- [x] P5 Investors, agreements (≤ 100%), capital, investor ledger
- [x] P6 Cost allocation runs and flat profit calculations
- [x] P7 Profit approval, payable, distribution, payout
- [x] P8 Integration, reports, reconciliation, portal DTOs, release
