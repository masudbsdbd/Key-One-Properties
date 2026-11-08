# KEY ONE PROERP — PHASE 3 DEVELOPMENT BLUEPRINT

**Project:** Key One Properties Ltd. — Real Estate ERP & Construction Management System  
**Version:** 1.0 — 08 November 2026  
**Purpose:** Claude Code implementation specification, database design, UI/UX standards, business workflows, testing, and sequential prompts.  
**Dependency:** Extend the EXISTING Phase 1 + Phase 2 Laravel application. Never create a second application or replace existing schema without a migration plan.

---

## 1. Scope and locked business decisions

**Three modules**

- **M10 Booking & Sales Management:** flat reservation, booking, sale/agreement, booking cancellation, refund, price changes and history, registration tracking, handover preparation.
- **M11 Installment & Collection Management:** installment plans, due/overdue, partial/advance payments, per-customer configurable late fee, rescheduling, collection, receipts, customer operational statement.
- **M12 Investor Management:** multiple investors per flat, capital commitments/contributions, per-flat profit shares, flat cost and revenue attribution, investor operational ledger, proposed profit, permission-controlled approval, payable and actual distribution.

**Confirmed requirements**

1. Multiple investors may invest in the same flat. The sum of active profit-share percentages on a flat **must never exceed 100%**; remaining share belongs to company unless explicitly defined otherwise.
2. **Flat-wise investor profit** is the initial implementation; data architecture must allow **project-wise profit sharing later**, without implementing it now.
3. Shared construction costs are allocated to flats **by saleable area**. Flat direct costs and approved shared costs must be distinguishable.
4. Profit calculation is not payment. **Profit distribution requires explicit manual approval** by an Admin or other user with the dynamic `investor.profit.approve` permission. Approved payable and actual paid are separate states/events.
5. Booking cancellation, refunds, and price changes are included. **Flat-to-flat transfer** and **landowner/developer share** are excluded for now.
6. Installments support **partial payment, advance payment, overdue tracking and rescheduling**. Late fee is **configurable individually for a customer** (including zero fee).
7. Customer portal (Phase 4) will be **read-only statement**; Phase 3 prepares secure statement queries and DTOs but does not build portal.
8. Investor portal (Phase 4) may show flat-specific and project-wide cost/progress depending on permissions; Phase 3 prepares visibility-safe data contracts but does not build portal.
9. Full Double-entry Accounting UI/GL is Phase 5; **accounting-ready immutable financial events, reconciliation and references are mandatory now**.
10. Phase 1 UI and Phase 2 component conventions must be preserved. Laravel + Blade + Tailwind + jQuery/AJAX + Select2; no React/Vue/Livewire. Searchable selector for **more than 7 options**, AJAX remote search for large lists.
11. Fixed-term investment (e.g., 3 years with no interest on early withdrawal) is **future scope only**. Do not implement early-withdrawal interest rules.

**Recommended defaults — NOT yet client-confirmed**

- Booking requires an available flat; atomic state lock prevents simultaneous bookings.
- Reservation/booking money and later payments are separately recorded, with no duplicated collection.
- Registration is a tracked workflow: agreement -> registration pending -> registered -> handover ready -> handed over; retain number, date, document and audit history. Actual statutory requirements are outside this blueprint and must be validated with client/legal team.
- Booking cancellation does not automatically issue refund; it creates a separately approved refund request and settlement.
- Late fees default to zero, can be fixed or percentage for an individual customer/plan, and are calculated on overdue installments according to explicit configured rules. Never retroactively change posted fees silently.
- Rescheduling affects only unpaid future installments; already collected/posted history is immutable.
- Investor capital and profit are separate balances. Capital refund/return needs its own transaction and permission.
- For flat-wise profit, use realized revenue and allocated direct/shared costs, with an explicit profit calculation version/snapshot; show provisional profit before final approval. **Revenue recognition and eligibility for profit approval need a separate business decision**, not an assumption that booking equals earned revenue.

---

## 2. Architecture and module boundaries

Continue Phase 1/2 namespace and coding style. Suggested logical folders (adapt to actual repository):

```
app/Modules/
  Sales/{Controllers,Requests,Policies,Services,Models,Events,DTOs}
  Collections/{Controllers,Requests,Policies,Services,Models,Events,DTOs}
  Investors/{Controllers,Requests,Policies,Services,Models,Events,DTOs}
app/Support/FinancialEvents/
resources/views/erp/{sales,collections,investors}/
resources/js/erp/{sales,collections,investors}/
tests/{Feature,Unit}/Phase3/
```

**Services and invariants:** `FlatAvailabilityService`, `BookingService`, `SalesAgreementService`, `RefundService`, `InstallmentScheduleService`, `PaymentAllocationService`, `LateFeeService`, `InvestorAllocationService`, `FlatCostAllocationService`, `ProfitCalculationService`, `ProfitApprovalService`, `InvestorPayoutService`.

- Controllers are thin. FormRequests validate. Policies authorize at record/project/flat level. State changes go through Services.
- Use `DB::transaction` and `lockForUpdate` on flat, active booking, installment balances, investor share allocation, profit approval and payout; support idempotency keys and optimistic `version` where appropriate.
- Money `DECIMAL(18,2)`, percentage `DECIMAL(7,4)`, area `DECIMAL(14,3)` and calculation rates `DECIMAL(18,6)`; never FLOAT. Round posted monetary amounts to two decimal places with explicit remainder allocation.
- Store timestamps UTC, display Asia/Dhaka and BDT; all money/date inputs validated server-side.
- Posted payments, refunds, approved profit and investor payouts are **immutable**; use linked reversal/adjustment, not edit/delete.
- Reuse Phase 1 `projects`, `flats`, `customers`, `users`, permissions, audit logs and Phase 2 cost attribution/financial events. Inspect actual schema first. Do not create duplicate customer or flat tables.
- Maintain append-only source events and reconcile operational subledgers; do not fabricate Phase 5 journal entries.

---

## 3. Premium UI/UX requirements

### Design

Clean, premium, understated **light mode**. Continue Phase 1 tokens and components: spacious white cards, soft neutral backgrounds, navy typography, restrained blue/teal accent, subtle dividers, consistent radii and spacing. No clutter, gradients everywhere or visually noisy charts. Fast keyboard-friendly interactions and strong mobile responsiveness.

### Shared behavior

- **More than 7 options:** Select2/searchable select. Large sets (customers, flats, projects, investors, users): remote AJAX query, 300ms debounce, pagination 25/page, selected value hydration, permission-scoped results, no full table dump.
- AJAX create/edit/approve/cancel/allocate/collect/pay/filter/search/sort/paginate. Update affected table rows, KPI counters, statement and timeline immediately **after server confirms**. No full page reload for routine actions.
- Loading skeleton, toast, inline 422 validation, accessible confirmation dialog, duplicate-click prevention, 409 conflict recovery, 401/403/419 handling.
- Use responsive right-side drawer for forms; confirmation modal for money/approval actions; large transaction wizard for multi-step flows.
- Show all currency values with thousands separators; show negative/overdue values unambiguously. Support download/print for receipt/statement/booking documents with access control.
- Never optimistic-update money, flat availability, profit approval or balances before confirmed server response.

### Screens

**Booking & Sales:** Sales overview; flat availability board; booking list; new booking wizard; booking detail tabs (Overview, Agreement, Payments, History); cancellation/refund workflow; price revision timeline; registration/handover tracking.

**Installment & Collection:** collection dashboard; customer dues; installment schedule builder; collection drawer with allocation preview; customer statement; overdue aging; late fee editor; reschedule comparison/approval; money receipt viewer.

**Investor:** investor directory; investor profile tabs (Overview, Flat Investments, Capital Transactions, Profit, Ledger, Activity); flat investment allocation editor; flat cost/profit worksheet; approval queue; profit payout detail; reconciliation view.

---

## 4. Proposed database structure

**IMPORTANT:** These are proposed tables and columns, not a blind migration script. Claude must first inspect Phase 1/2 migrations, reuse existing equivalents, and produce a schema compatibility report. Use `BIGINT UNSIGNED` or the existing project ID convention consistently. Every table includes primary key, created_at/updated_at where applicable; immutable event tables use created_at and posting metadata. FKs, indexes, unique keys and status constraints must be implemented.

### 4.1 Sales / booking

`flat_price_histories`
- `id`, `flat_id` FK, `old_price DECIMAL(18,2) NULL`, `new_price DECIMAL(18,2)`, `effective_at`, `reason`, `changed_by` FK, `approved_by` nullable, `created_at`.
- Only approved revisions update current flat asking price; agreed sale price must remain fixed unless an approved amendment.

`flat_bookings`
- `id`, `booking_no UNIQUE`, `project_id` FK, `flat_id` FK, `customer_id` FK, `booking_date`, `booking_expires_at NULL`, `asking_price_snapshot`, `agreed_price`, `booking_amount_expected`, `status` (draft/pending/confirmed/cancel_requested/cancelled/converted_to_sale), `notes`, `created_by`, `approved_by NULL`, `approved_at NULL`, `cancelled_at NULL`, `version`, timestamps.
- Prevent >1 active confirmed booking/sale per flat. Because MySQL partial unique constraints are limited, enforce via an `active_flat_claims` table with `flat_id UNIQUE`, claimant type/id, and locked state transitions, or an equivalent atomic unique-slot mechanism. Do not rely only on application prechecks.

`flat_sales`
- `id`, `sale_no UNIQUE`, `booking_id UNIQUE NULL`, `project_id`, `flat_id`, `customer_id`, `agreement_date`, `sale_price`, `discount_amount`, `net_contract_value`, `status` (draft/approved/registration_pending/registered/handover_ready/handed_over/cancelled), `approved_by NULL`, `approved_at NULL`, `version`, timestamps.
- Enforce at most one active sale per flat through same claim mechanism. Sale and booking are distinct; do not duplicate booking payment.

`sale_agreements`
- `id`, `sale_id`, `agreement_no UNIQUE`, `signed_at NULL`, `document_file_id NULL`, `status`, `notes`, timestamps. Store private files through Phase 1 file service.

`booking_cancellations`
- `id`, `booking_id`, `reason`, `requested_by`, `requested_at`, `approved_by NULL`, `approved_at NULL`, `status` (requested/approved/rejected/completed), `refund_expected DECIMAL(18,2)`, timestamps.

`customer_refunds`
- `id`, `refund_no UNIQUE`, `customer_id`, `booking_id NULL`, `sale_id NULL`, `amount`, `reason`, `status` (draft/pending/approved/paid/rejected/reversed), `requested_by`, `approved_by NULL`, `approved_at NULL`, `paid_at NULL`, `payment_method NULL`, `payment_reference NULL`, `idempotency_key UNIQUE`, `reversal_of_id NULL`, timestamps.

`flat_registrations`
- `id`, `sale_id UNIQUE`, `status` (pending/in_process/registered), `registration_no NULL`, `registered_at NULL`, `registration_cost NULL`, `document_file_id NULL`, `notes`, `updated_by`, timestamps.

`flat_handovers`
- `id`, `sale_id UNIQUE`, `status` (pending/ready/completed), `scheduled_at NULL`, `completed_at NULL`, `checklist_json`, `acknowledgement_file_id NULL`, `handled_by NULL`, `notes`, timestamps. Handover workflow may be finalized later, but basic tracking belongs to sale lifecycle.

### 4.2 Installment and collection

`installment_plans`
- `id`, `plan_no UNIQUE`, `sale_id FK`, `customer_id FK`, `principal_amount`, `down_payment_amount`, `installment_frequency` (monthly/custom), `start_date`, `status` (draft/active/rescheduled/completed/cancelled), `version`, `created_by`, `approved_by NULL`, timestamps.

`installment_schedule_items`
- `id`, `plan_id`, `sequence_no`, `due_date`, `principal_due`, `fee_due DEFAULT 0`, `principal_paid DEFAULT 0`, `fee_paid DEFAULT 0`, `status` (upcoming/partial/paid/overdue), timestamps, UNIQUE(plan_id,sequence_no). Treat paid fields as reconciled cached totals, not source of truth.

`customer_payments`
- `id`, `payment_no UNIQUE`, `customer_id`, `sale_id NULL`, `booking_id NULL`, `payment_date`, `amount`, `method` (cash/bank/other), `reference_no NULL`, `status` (draft/pending/approved/posted/reversed), `received_by`, `approved_by NULL`, `posted_at NULL`, `idempotency_key UNIQUE`, `reversal_of_id NULL`, timestamps.
- A payment must have a defined purpose. Booking money may be allocated toward sale balance through explicit linked allocation, never duplicated as another payment.

`payment_allocations`
- `id`, `payment_id`, `schedule_item_id NULL`, `allocation_type` (booking/principal/late_fee/unapplied_advance), `amount`, `created_at`. Sum allocations <= payment amount; schedule allocation <= remaining due, using locked rows. Design a safe unapplied advance balance.

`customer_late_fee_policies`
- `id`, `customer_id`, `sale_id NULL`, `fee_type` (none/fixed/percent), `fee_value`, `grace_days DEFAULT 0`, `frequency` (once/per_month), `effective_from`, `effective_to NULL`, `approved_by NULL`, timestamps. Per-customer overrides; exact fee base must be documented before enabling percentage calculation.

`late_fee_assessments`
- `id`, `schedule_item_id`, `policy_id`, `assessed_on`, `amount`, `status` (posted/waived/reversed), `waived_by NULL`, `reason NULL`, `idempotency_key UNIQUE`, timestamps. No repeated accidental charging for same policy/period.

`installment_reschedules`
- `id`, `plan_id`, `reason`, `old_schedule_snapshot JSON`, `new_schedule_snapshot JSON`, `status` (requested/approved/rejected/applied), `requested_by`, `approved_by NULL`, `applied_at NULL`, timestamps. Preserve prior schedules and paid items.

`customer_statement_entries`
- `id`, `customer_id`, `sale_id NULL`, `entry_date`, `source_type`, `source_id`, `charge_amount DEFAULT 0`, `credit_amount DEFAULT 0`, `description`, `posted_at`, `reversal_of_id NULL`, timestamps. Unique source event key. Running balance computed with deterministic ordering.

`money_receipts`
- `id`, `receipt_no UNIQUE`, `payment_id UNIQUE`, `issued_at`, `issued_by`, `document_file_id NULL`, timestamps. Reversed payments retain voided receipt history.

### 4.3 Investors and profit

`investors`
- `id`, `investor_code UNIQUE`, `name`, `mobile NULL`, `email NULL`, `address NULL`, `status`, `portal_user_id NULL` FK to users, timestamps. Reuse Phase 1 people/contacts if suitable; do not auto-create portal access.

`investor_flat_agreements`
- `id`, `agreement_no UNIQUE`, `investor_id`, `project_id`, `flat_id`, `committed_capital`, `profit_share_percent DECIMAL(7,4)`, `effective_from`, `effective_to NULL`, `status` (draft/active/closed/cancelled), `agreement_file_id NULL`, `approved_by NULL`, `approved_at NULL`, `version`, timestamps.
- Validate sum of ACTIVE effective profit shares per flat <=100.0000 **inside locked DB transaction**. Store historical versions; do not overwrite percentages after distribution.
- Data model should support future `investment_scope` (flat/project) via a deliberate extensibility point, but do not enable project-wide profit now.

`investor_capital_transactions`
- `id`, `transaction_no UNIQUE`, `investor_id`, `agreement_id`, `flat_id`, `project_id`, `transaction_type` (contribution/capital_return/reversal), `amount`, `transaction_date`, `payment_method NULL`, `reference_no NULL`, `status` (draft/approved/posted/reversed), `approved_by NULL`, `posted_at NULL`, `idempotency_key UNIQUE`, `reversal_of_id NULL`, timestamps.

`flat_cost_allocations`
- `id`, `project_id`, `flat_id`, `cost_run_id`, `source_cost_event_id`, `allocation_type` (direct/shared), `basis` (direct/saleable_area), `allocated_amount`, `allocated_at`, `reversal_of_id NULL`, timestamps; unique per source event/flat/run/type. Link to Phase 2 approved cost events. Allocation should sum to eligible shared project cost within rounding tolerance.

`flat_cost_allocation_runs`
- `id`, `project_id`, `run_no UNIQUE`, `total_saleable_area`, `eligible_shared_cost`, `calculation_version`, `status` (draft/approved/superseded), `approved_by NULL`, `approved_at NULL`, `snapshot_json`, timestamps. Never silently change approved historical runs when flat areas/costs change.

`flat_profit_calculations`
- `id`, `calculation_no UNIQUE`, `flat_id`, `sale_id NULL`, `revenue_amount`, `direct_cost_amount`, `shared_cost_amount`, `other_approved_cost_amount`, `net_profit_amount`, `basis_snapshot JSON`, `status` (draft/submitted/approved/rejected/superseded), `calculated_by`, `approved_by NULL`, `approved_at NULL`, `version`, timestamps.
- Calculations must identify revenue eligibility and exclude unearned revenue/duplicate expenses; loss results in no positive profit distribution unless separately agreed.

`investor_profit_allocations`
- `id`, `profit_calculation_id`, `investor_agreement_id`, `investor_id`, `flat_id`, `share_percent_snapshot`, `profit_amount`, `status` (proposed/approved/reversed), `approved_by NULL`, `approved_at NULL`, `reversal_of_id NULL`, timestamps; UNIQUE(profit_calculation_id,investor_agreement_id). Sum <= distributable positive profit.

`investor_profit_distributions`
- `id`, `distribution_no UNIQUE`, `investor_profit_allocation_id`, `investor_id`, `amount`, `status` (draft/pending_approval/approved/partially_paid/paid/rejected/reversed), `requested_by`, `approved_by NULL`, `approved_at NULL`, `idempotency_key UNIQUE`, timestamps.

`investor_payouts`
- `id`, `payout_no UNIQUE`, `distribution_id`, `investor_id`, `amount`, `paid_at`, `payment_method`, `payment_reference NULL`, `status` (posted/reversed), `posted_by`, `idempotency_key UNIQUE`, `reversal_of_id NULL`, timestamps. Total payouts cannot exceed approved distribution amount.

`investor_ledger_entries`
- `id`, `investor_id`, `agreement_id NULL`, `flat_id NULL`, `source_type`, `source_id`, `entry_date`, `entry_kind` (capital_in/capital_out/profit_payable/profit_paid/reversal), `amount`, `posted_at`, `reversal_of_id NULL`, timestamps. Keep capital and profit payable sub-balances separate; do not present as Phase 5 GL.

### 4.4 Shared system integrations

- Reuse Phase 1 activity/audit/attachments and dynamic permission tables.
- Reuse Phase 2 `project_cost_allocations`, `stock_movements`, `business_financial_events` (or actual equivalent), contractor/supplier costs. Never derive flat profit by naively summing bills **and** stock receipts **and** stock consumption.
- For every posted payment, refund, investor contribution, capital return, approved profit payable and payout, emit one unique immutable accounting-ready event with project/flat/customer/investor reference, amount, effective date, source ID, reversal link and proposed account mapping metadata.
- Add report indexes: `(project_id,status,created_at)`, `(flat_id,status)`, `(customer_id,posted_at)`, `(sale_id,due_date)`, `(investor_id,flat_id)`, `(flat_id,status,effective_from)`, and source idempotency unique keys.

---

## 5. Core business workflows

### 5.1 Booking and sale

1. Search/select available flat (remote Select2) and customer.
2. Show current asking price, saleable area, status, previous price revisions and any active claim.
3. Enter proposed sale price, booking amount and terms; save draft via AJAX.
4. Submit/approve booking; acquire flat lock + unique claim; mark unavailable. If concurrent booking, return 409 and refresh status.
5. Receive booking payment through shared collection service, issue receipt once posted.
6. Convert confirmed booking to sale/agreement without duplicating payment or creating another active claim.
7. Registration and handover tracked as separate statuses. No flat transfer in Phase 3.
8. Cancellation requires approval and releases claim only when cancellation settlement policy allows; refunds approved and paid separately.

### 5.2 Installment and collection

1. Create installment plan for approved sale. Principal schedule + down payment + recognized booking credit must reconcile to contract value.
2. Schedule monthly or custom due dates; preview and approve.
3. Collect partial/full/advance payments. Lock relevant items, allocate to outstanding dues in deterministic order; store any unapplied advance separately.
4. Generate receipt and update statement immediately after posting.
5. Mark overdue based on due date and unpaid balance, with no automatic destructive mutation of historical schedule.
6. Apply customer-specific late fee policy only when configured; log each fee assessment and waiver.
7. Reschedule remaining unpaid amounts with old/new schedule snapshot and authorized approval. Never rewrite paid history.

### 5.3 Flat cost and investor profit

1. Read Phase 2 approved cost attribution events; separate direct flat costs and eligible project-wide shared costs.
2. Allocate shared cost by `flat.saleable_area / project.total_eligible_saleable_area`, using a frozen approved calculation run and deterministic rounding.
3. Determine eligible flat revenue from approved sale/collection and documented recognition policy; calculate provisional net profit, snapshot source references.
4. Validate active investor agreements for that flat (sum <= 100%). Example: Investor A 30%, B 25%, company 45%.
5. Produce investor proposed allocations; **no payout yet**.
6. Authorized user with `investor.profit.approve` reviews and manually approves calculation/allocation/distribution per policy; record actor/time/version.
7. Record approved investor profit payable; actual payout is separately approved/posted and reflected in investor ledger.
8. If costs change after approved calculation, create a new adjustment/recalculation run; never silently rewrite prior approved payouts.

### 5.4 Profit recognition policy — required pre-production decision

Do not assume that a flat booking or a cash collection is automatically recognized as accounting revenue or distributable profit. Present configurable options (sale/registration/collection milestone and final manual eligibility) for client/finance approval. Until decided, calculations are **provisional** and profit approval is blocked or requires explicitly documented override. Full revenue recognition and tax rules to be aligned with Phase 5 accounting.

---

## 6. Dynamic permissions

Proposed seeds, mapped to Phase 1 naming conventions:

- `sales.booking.view`, `.create`, `.edit`, `.submit`, `.approve`, `.cancel.request`, `.cancel.approve`
- `sales.sale.view`, `.create`, `.approve`, `.agreement.manage`, `.registration.manage`, `.handover.manage`
- `sales.price.view`, `.change.request`, `.change.approve`, `.history.view`
- `sales.refund.view`, `.request`, `.approve`, `.pay`, `.reverse`
- `collections.plan.view`, `.create`, `.approve`, `.reschedule.request`, `.reschedule.approve`
- `collections.payment.view`, `.collect`, `.approve`, `.reverse`, `.receipt.view`, `.statement.export`
- `collections.latefee.manage`, `.waive`, `.view`
- `investor.view`, `.create`, `.edit`, `.ledger.view`, `.ledger.export`
- `investor.agreement.create`, `.approve`, `.edit`, `.close`
- `investor.capital.record`, `.approve`, `.return`
- `investor.cost.view`, `.cost.allocate`, `.profit.calculate`, `.profit.submit`, `.profit.approve`, `.profit.distribution.create`, `.profit.distribution.approve`, `.profit.payout.post`, `.profit.reverse`

**Important:** `investor.profit.approve` must authorize the appropriate manual profit approval action. Avoid inconsistent duplicate permission names; use aliases only for migration if necessary. Enforce project/flat ownership and scope on every endpoint and query. Optional self-approval restriction recommended for financial actions. Investor and customer portal roles get **no internal module access** by default.

---

## 7. AJAX route contract (illustrative; adapt to existing route naming)

All under authenticated ERP namespace; policy-check on every request. JSON response shape must match Phase 1. Return 409 for stale version, flat unavailable, over-allocation or invalid state; 422 for validation.

- `GET /erp/flats/search?q=&project_id=&page=`
- `GET /erp/customers/search?q=&page=`
- `GET|POST /erp/bookings`; `PATCH /erp/bookings/{id}`; `POST /erp/bookings/{id}/submit|approve|cancel-request|cancel-approve|convert-to-sale`
- `POST /erp/sales`; `POST /erp/sales/{id}/approve`; `POST /erp/sales/{id}/registration`; `POST /erp/sales/{id}/handover`
- `POST /erp/flats/{id}/price-revisions`; `POST /erp/price-revisions/{id}/approve`
- `POST /erp/refunds`; `POST /erp/refunds/{id}/approve|pay|reverse`
- `GET|POST /erp/installment-plans`; `POST /erp/installment-plans/{id}/approve|reschedule`
- `POST /erp/customer-payments`; `POST /erp/customer-payments/{id}/post|reverse`; `GET /erp/customers/{id}/statement`
- `POST /erp/late-fee-policies`; `POST /erp/late-fees/{id}/waive`
- `GET|POST /erp/investors`; `GET /erp/investors/{id}/ledger`; `POST /erp/investor-agreements`; `POST /erp/investor-agreements/{id}/approve`
- `POST /erp/investor-capital-transactions`; `POST /erp/flat-cost-runs`; `POST /erp/flat-cost-runs/{id}/approve`
- `POST /erp/flat-profit-calculations`; `POST /erp/flat-profit-calculations/{id}/submit|approve`
- `POST /erp/investor-profit-distributions`; `POST /erp/investor-profit-distributions/{id}/approve`; `POST /erp/investor-payouts`

`|` means separate action routes, not a literal URL. Use POST/PATCH as appropriate and CSRF headers.

---

## 8. Testing and acceptance scenarios

1. Two users book the same flat concurrently: exactly one active booking; other receives 409.
2. Cancel booking: history remains, claim release follows policy, refund is NOT automatically paid.
3. Approved price change keeps original booking/sale price snapshot and audit log.
4. Booking payment credited to sale once; no double revenue/collection.
5. Installment plan principal + down payment + recognized booking credit reconciles to sale value.
6. Partial payment updates due, statement and receipt correctly.
7. Advance payment allocates to existing due and preserves unapplied advance without negative due.
8. Customer-specific zero/fixed/percentage late fee works as configured, assessment is idempotent.
9. Reschedule leaves paid installments and original history intact.
10. Investor A 30%, B 40%, C 30% accepted; adding 1% rejected even under concurrent submissions.
11. Shared cost 2,000,000 across 20,000 sqft => 1,000 sqft flat gets 100,000, subject to rounding and eligibility.
12. Sum of flat allocations equals shared cost to the cent after deterministic remainder assignment.
13. Source Phase 2 inventory/contractor/expense cost is counted exactly once in flat profit.
14. Profit calculation draft does not create payable/payout; approval creates payable, payout reduces payable.
15. User lacking `investor.profit.approve` cannot approve by direct URL/API.
16. Payout cannot exceed approved unpaid amount; concurrent payouts safe.
17. Recalculation preserves approved history and creates versioned adjustment.
18. Customer statement access and investor data query respect project/flat scope and future portal visibility flags.
19. AJAX list/create/edit/approve/collect flows work without full reload; >7 options searchable; remote selectors paginated.
20. Reversed payment/refund/payout retains original event and issues compensating event.
21. Financial events and operational ledgers reconcile and are not mislabeled as final double-entry GL.
22. Run migration rollback/fresh-install tests, feature tests, policy tests, concurrency/idempotency tests and browser interaction tests.

---

# 9. CLAUDE CODE PROMPTS — EXECUTE SEQUENTIALLY

## MASTER PROMPT — project continuation and analysis

```text
You are the lead Laravel architect, database engineer and premium ERP UI engineer for Key One Properties Ltd.

Read KEY_ONE_PHASE_1_BLUEPRINT.md, KEY_ONE_PHASE_2_BLUEPRINT.md and KEY_ONE_PHASE_3_BLUEPRINT.md completely. Inspect the actual Laravel codebase, existing database migrations, Eloquent models, services, policies, UI components, routes and tests. Phase 3 extends Phase 1+2; do not start a new application.

Scope exactly 3 modules: Booking & Sales, Installment & Collection, Investor Management. Preserve Laravel + Blade + Tailwind + jQuery/AJAX + Select2, Phase 1 design system and existing architecture. Every dropdown with >7 options must be searchable; use remote AJAX search for large data. Make routine operations instant without page reload. Maintain strong server-side authorization, project/flat scoping, audit trail, CSRF, row locking, idempotency, immutable posted financial history and DECIMAL money.

Confirmed: multiple investors per flat, sum of investor profit shares <=100%, shared construction costs allocated by saleable area, flat-wise profit now (project-wise extensibility later), profit distribution only after manual approval by authorized Admin/user, partial and advance installments, customer-specific late fee, rescheduling, booking cancellation/refund/price changes, and accounting-ready financial events from day one.

Excluded now: flat transfer, landowner/developer share, fixed-term early-withdrawal interest, Phase 4 portals, and full Phase 5 double-entry GL.

Before coding, produce: (1) Phase 1/2 compatibility audit, (2) exact schema diff, (3) financial-event and project-cost integration plan, (4) permissions and concurrency design, (5) UI component plan, (6) unresolved business-policy decisions and safe defaults, (7) sequential implementation checklist. Do not invent missing Phase 1/2 tables. Stop for approval if migration is destructive or accounting policy unclear. After each implementation prompt report files changed, tests actually executed, outcomes and risks.
```

## PROMPT 1 — Premium UI extension and shared AJAX components

```text
Implement Phase 3 navigation, page shells and reusable components consistent with Phase 1/2 premium clean light theme. Add Sales, Collections and Investors sidebar groups gated by permissions. Create: searchable flat/customer/investor Select2 components (remote AJAX with 25/page), booking wizard, sale price card, installment schedule editor, payment allocation preview, money receipt viewer, refund approval modal, investor allocation editor, flat profit breakdown, approval queue, financial ledger, activity timeline, skeletons and responsive mobile cards.

Use centralized jQuery AJAX wrapper, CSRF, loading/disable, 422 inline errors, 409 conflicts, 401/403/419 handling, toast and targeted DOM updates. Never optimistically post money. Test keyboard navigation, responsive layouts and no-full-reload flows. Preserve existing components rather than rebuilding them.
```

## PROMPT 2 — Database migrations, models, permissions and invariants

```text
Compare the actual Phase 1/2 schema with the Phase 3 proposed database section. Create safe incremental migrations, models, relations, casts, policies, factories, permission seeds, unique indexes and audit fields. Reuse existing flat/customer/project/financial-event tables. Add an atomic unique active-flat-claim mechanism. Add investor share allocation concurrency protection. Enforce immutable posted financial records and linked reversals. DECIMAL only for money, area and percentages.

Write schema/relationship documentation, migration tests, indexes and invariants. Do not migrate destructively. Show differences between proposed and actual tables and get approval for any breaking change.
```

## PROMPT 3 — Booking, Sales, Cancellation, Refund, Price History

```text
Implement flat availability locking, booking draft/submit/approve, booking-to-sale conversion, agreement record, booking cancellation, separately approved refund and payment, approved price revisions with history, registration status and handover preparation. Preserve original sale price snapshot. Avoid duplicate booking money or active flat claims.

Build premium Sales dashboard, availability board, booking wizard, booking details, cancellation/refund drawer, price history and agreement tabs. All suitable actions AJAX, permissions and project scope checked. Test simultaneous booking, refund idempotency, cancellation state transitions, receipt linkage and authorization.
```

## PROMPT 4 — Installment Schedule, Collections and Customer Ledger

```text
Implement installment plan generation (monthly/custom), down payment/booking credit reconciliation, partial and advance payments, allocation to due items, unapplied advance, customer-specific late fee (none/fixed/percent), overdue status, remaining-installment rescheduling with snapshots, money receipts and customer operational statement.

Keep payment posting atomic and idempotent. Do not alter already paid installment history. Separate fee assessment, fee waiver and payment. Show real-time schedule preview, due counters, allocation preview, customer statement and overdue aging; update via AJAX without page reload. Test rounding, double submit, concurrent collection, overpayment, permissions and statement reconciliation.
```

## PROMPT 5 — Investor Directory, Multiple Flat Investors and Capital Ledger

```text
Implement investor profiles, flat investment agreements, per-flat percentage shares, capital contributions/returns, documents, investor operational ledger and activity history. A flat can have multiple investors, but sum of all active effective profit share percentages <=100.0000; enforce transactionally with a lock and concurrency tests. Capital contributed and profit payable must be separate balances. Preserve agreement versions and history.

Build investor directory, detail tabs, flat-investor allocation editor with live 100% gauge, agreement approval, capital transaction form and ledger. All appropriate actions AJAX; large dropdowns remote Select2; permissions and project/flat scopes mandatory. Do not implement fixed-term interest or project-wide investment distribution yet.
```

## PROMPT 6 — Saleable Area Cost Allocation and Flat Profit Calculation

```text
Integrate Phase 2 approved construction, labour, material consumption, contractor and project expense cost events. Distinguish direct flat costs from eligible shared costs. Allocate shared construction cost proportionally to flat saleable area using versioned calculation runs, frozen area snapshots and deterministic rounding. Validate sum of allocations against shared cost. Avoid double counting supplier bills, material receipts/consumption, contractor bills and expenses.

Create flat profit calculation draft with revenue eligibility policy, direct cost, allocated shared cost, other approved cost, net profit and source references. Clearly mark provisional vs approved. Do not approve/distribute profit until revenue recognition and eligible cost policy are explicitly configured. Build detailed transparent cost worksheet and profit preview with drill-down. Add reconciliation and change-history tests.
```

## PROMPT 7 — Profit Approval, Payable and Investor Distribution

```text
Implement investor profit allocation snapshots from an approved flat profit calculation and the effective flat-investor agreements. Validate total investor allocations <= available positive distributable profit and share sum <=100%. Keep company retained share explicit. Workflow: draft calculation -> submit -> authorized manual approve -> create approved investor payable -> create distribution request -> authorized approve -> actual payout -> ledger update.

Require `investor.profit.approve` for the relevant manual approval; separate payout permission. Approval does not equal payment. Protect all money flows with idempotency, row locks, immutable events, reversal/adjustment and audit actor/timestamps. Build approval queue, side-by-side calculation comparison, payable and payout views. Test concurrent approvals, overpayment, stale calculation, missing permission and reconciliation.
```

## PROMPT 8 — Cross-module integration, reports, tests and release

```text
Integrate Phase 3 with Phase 1 flat/customer/project management and Phase 2 construction/project cost/financial events. Build permission-scoped sales, due/collection, flat profit and investor statements; PDF/Excel exports only where supported by existing infrastructure. Expose read-only, permission-safe statement/progress/cost DTO contracts for Phase 4 portals without building portals.

Run fresh install and upgrade migrations, unit/feature/policy tests, concurrency/idempotency tests, ledger/cost reconciliation, responsive browser checks and AJAX flows. Verify no N+1 queries, paginated searches, private document authorization, CSRF and audit trail. Produce release notes, schema diff, verified test results, outstanding accounting policy decisions and Phase 4/5 integration contracts. Do not claim complete if critical tests fail.
```

---

## 10. Execution checklist

1. Put this file at project root alongside Phase 1 and Phase 2 blueprints.
2. Give Claude **MASTER PROMPT**; review compatibility report and policy gaps before coding.
3. Run **Prompts 1 through 8**, one by one. Test and commit after each accepted milestone.
4. Keep database backup before production migrations and avoid destructive changes.
5. Document approved deviations in `docs/decisions/phase-3.md`.
6. Demo a full journey: available flat -> booking -> sale -> installments -> partial payment -> overdue/advance -> investor contributions -> flat cost allocation -> proposed profit -> manual approval -> payout -> reconciled ledgers.

## 11. Critical policy questions to finalize before live financial posting

- **Profit eligibility/revenue recognition:** when may a flat's profit become distributable (registration, full collection, other milestone, and approval)? Manual admin approval alone is not an accounting recognition policy.
- **Profit loss treatment:** if a flat makes a loss, how are investors affected? Default for this blueprint: no positive profit distribution; do not automatically charge investors for loss.
- **Cost pool eligibility:** what project expenses are shared, which are flat-direct, and what happens to cost allocations when flats are added/area revised after an approved run?
- **Cancellation/refund policy:** whether booking charge is refundable, deductions, and when a flat becomes available again.
- **Late fee specifics:** percentage base, grace period, recurrence, waiver and whether late fees are treated as distributable flat revenue.
- **Payment application order:** overdue principal vs fees vs upcoming principal; recommend a documented configurable order with clear customer statements.
- **Investment share changes:** whether a new investor can join after revenue/cost milestones and whether profit is prorated by time/capital; recommend agreement effective dates and explicit versioning.
- **Cash/bank settlement:** exact payment methods, reversal authorization and reconciliation integration for Phase 5.

**Definition of Done:** all three Phase 3 modules work end-to-end with the existing ERP, secure AJAX UX, searchable selectors, audit trails, atomic financial transactions, correct flat availability, customer statement reconciliation, saleable-area shared-cost allocation, investor share limit, manual profit approval, separated payable/payout and accounting-ready immutable events.
