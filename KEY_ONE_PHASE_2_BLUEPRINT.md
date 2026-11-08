# KEY ONE PROPERTIES ERP — PHASE 2 DEVELOPMENT BLUEPRINT
Version 1.0 | 08 November 2026 | Implementation specification and sequential prompts

> **Scope:** Phase 2 only. Continue the existing Phase 1 Laravel project and preserve its migrations, routes, design system, policies and conventions. Do not build a second application. This is a proposed implementation blueprint: the confirmed requirements are distinguished from recommended defaults.
>
> Decisions taken while implementing are recorded in `docs/decisions/phase-2.md`.

## 0. Business context and binding decisions

Phase 2 covers **five logical modules**:
1. Construction Management
2. Material & Warehouse Management
3. Supplier & Purchase Management
4. Contractor Management
5. Expense Management

**Confirmed business requirements**
- A project may temporarily **borrow materials** from another project, return them (including partial return), or convert outstanding borrowed quantities into a **permanent transfer**.
- A permanent transfer decreases the **source project's attributed material cost** and increases the destination project's attributed material cost by the same valuation. Keep the entire request, approval, conversion, movement and accounting history.
- All relevant requests, approvals, rejection, issue, receipt, transfer and reversals are controlled by **dynamic permissions**.
- Supplier and contractor each have their own ledger and activity history.
- Expenses have two scopes: **general company expense** and **project-wise expense**.
- Construction progress and project stages must support later investor-portal visibility.
- Shared construction cost will later be allocated to flats **proportionally to saleable area**.
- Accounting-ready event/transaction structure is required now, but the full double-entry Accounting & Finance module is scheduled for Phase 5.
- The ERP is one Laravel application/database with Blade, Tailwind, jQuery/AJAX and a premium clean light design. Searchable Select2-like dropdowns for **more than 7 choices**; server-side AJAX search for large lists.

**Recommended Phase 2 defaults (configurable, subject to approval)**
- Inventory valuation: weighted average cost (WAC) per warehouse/material; never FLOAT.
- Material borrow is a **custody movement**, not a final cost transfer. Source project retains financial cost ownership until borrow is converted into permanent transfer or consumed under an explicitly approved costing rule. Destination physical stock is shown separately from owned stock.
- Source project cost decreases / destination cost increases on **permanent ownership transfer** using the transfer's locked valuation. If the material remains inventory, move attributed inventory value; if expensed, post a cost reallocation event. Never count the same cost twice.
- Default purchase lifecycle: Draft -> Submitted -> Approved -> Partially Received/Received -> Closed; reject/cancel with reason.
- Default expense lifecycle: Draft -> Submitted -> Approved -> Paid (payment is a separate event). General expenses have no project_id; project expenses require project_id.
- Contractor lifecycle: Work Order -> Measurement/Progress -> Running Bill -> Approval -> Payment -> Settlement. Retention is optional and configurable, default zero.
- No negative available stock; no hard-delete of posted financial/stock records; reversal or compensating adjustment with reason.

## 1. Implementation stack and architectural guardrails

- Existing Laravel + MySQL 8+, Blade, Tailwind CSS, jQuery, Select2; no React/Vue/Livewire.
- Follow Phase 1 module conventions, authorization middleware, policies, audit trail, file service, design tokens and AJAX JSON envelope.
- Structure modules in `app/Modules/<Module>/` **if Phase 1 already uses that**, otherwise follow the established layout; do not introduce incompatible package architecture.
- Services own state transitions, Controllers are thin, FormRequests validate, Policies authorize, Events/outbox record accounting-ready effects.
- All money `DECIMAL(18,2)`, quantity `DECIMAL(18,3)` (use `DECIMAL(18,4)` where needed for unit rates), percentages `DECIMAL(7,4)`, UTC timestamps; display BDT and Asia/Dhaka.
- Transactions + row locks for receiving, issuing, returning, converting, stock valuation, payment posting. Enforce idempotency keys for financial/stock commands.
- Never modify balances without a ledger movement; derive from immutable posted movements or maintain transactional snapshots with reconciliation.
- Soft-delete only drafts/master records as appropriate. Posted movements, approved bills and ledger entries are immutable, corrected via linked reversal.
- Project-level authorization must be enforced on every read/write and AJAX lookup, including nested resources and exports.
- Never treat client-supplied cost, permissions or balances as authoritative.

## 2. Premium UI/UX system — extend Phase 1

**Visual direction:** refined light ERP; ivory/white surfaces, deep navy text, restrained accent, subtle separators, 12–16px radius, generous whitespace, 8px spacing system, readable dense tables. Use Phase 1 tokens and shared Blade components; avoid design drift.

**Navigation:** sidebar groups: Construction, Inventory, Purchasing, Contractors, Expenses. Add role-gated submenus and dashboard counters. Project detail adds Construction, Materials, Purchases, Contractors and Expenses tabs.

**Reusable patterns:**
- Contextual action drawer (not full-page forms for quick operations); responsive full-screen drawer on mobile.
- Searchable Select2 for dropdowns with >7 options; remote search for materials, suppliers, contractors, projects, warehouses, work orders. Debounce 300 ms, server-side pagination (25 results), allow clear, preserve preselected edit values, keyboard support.
- AJAX list search/filter/sort/pagination; 250–350 ms debounce; abort stale requests; show skeletons and meaningful empty states.
- Instant create/edit/status changes via AJAX with inline 422 errors, toast, row update, summary refresh and no full reload.
- Confirmation dialogs for approve/issue/return/convert/reverse; show irreversible financial consequences and affected projects.
- Stock transfer wizard: source -> destination -> materials -> quantity/valuation preview -> approver -> confirm; show projected available stock and cost impact.
- Every ledger: opening balance (if imported), debits/credits or charges/payments clearly labelled, running balance, linked source document, activity timeline; do not confuse supplier/contractor operational statements with finalized general ledger.
- Responsive mobile views with stacked transaction cards; desktop tables have sticky header, compact density, column visibility and CSV/Excel export only if permissioned.
- All actions handle 401, 403, 409, 419, 422, 429 and 500; prevent duplicate clicks and race conditions. Optimistic UI only for easily reversible cosmetic actions.
- Accessibility: labels, focus rings, keyboard navigation, screen-reader status and no color-only meaning.

## 3. Module screen inventory

### M05 — Construction Management
- Construction overview dashboard: project progress, milestones, approved vs pending cost, labour, contractor and material summaries.
- Project construction plan: stages, milestones, tasks/work packages, planned dates, completion %, notes, attachments, responsible users.
- Progress update timeline with evidence images/files and approval if configured.
- Labour expense capture (individual daily entry or grouped labour batch), linked to project/work package.
- Project cost breakdown by direct material, labour, contractor and other approved expense.
- Investor-safe visibility flags and published progress snapshots for Phase 4 portal.

### M06 — Material & Warehouse
- Material catalog: SKU/code, name, category, unit, reorder level, active flag.
- Warehouses: project-linked warehouse and optional company warehouse; bin/location optional.
- Stock dashboard: physical quantity, owned quantity, borrowed-in/out, available quantity, valuation and low stock.
- Stock receive, issue/consumption, adjustment with approvals, movement ledger.
- Borrow requests, approval, issue, receipt, partial/full return, outstanding tracking.
- Convert outstanding borrow to permanent transfer with approval, frozen valuation, cost reallocation and complete traceability.
- Permanent direct project-to-project transfer, no self-transfer, no negative available stock.
- Printable transfer/borrow/return note, audit timeline.

### M07 — Supplier & Purchase
- Supplier directory, contacts, tax/registration fields optional, status and documents.
- Purchase requisition (simple optional draft), purchase order, approval, goods receipt, supplier invoice, purchase return.
- Partial receipt, invoice matching (basic), supplier advances/payments and outstanding ledger.
- Supplier detail: transaction statement, aging, purchases, payment history, activity and files.

### M08 — Contractor Management
- Contractor directory, contracts, scope, rates, documents, project linkage.
- Work order, progress/measurement entry, running bill, approval, advance adjustment, optional retention, payment and final settlement.
- Contractor ledger, outstanding amount, work history, linked progress and activity.

### M09 — Expense Management
- General expense (company-level) and project expense (project required), category, vendor/payee, attachments, date, amount, payment mode and status.
- Expense submission, approval, payment record, reversal, recurring templates optional but not required.
- Expense reports filtered by project, category, period, approval and payment status.
- No accidental double-counting of inventory receipt, contractor bill and expense entry. Expense source type must identify originating module.

## 4. Database structure — proposed migrations

**Schema conventions:** use existing Phase 1 `projects`, `users`, permissions, activity logs, file attachments, project stages and financial event foundation; adapt FK names to actual Phase 1 schema. Every mutable master table has timestamps and optional softDeletes. Every posted document includes `created_by`, `approved_by`, `approved_at` as applicable and `version` for optimistic concurrency.

### 4.1 Construction
- `construction_work_packages`: id PK, project_id FK, code (unique per project), name, description, stage_id nullable FK to Phase 1 stage, parent_id nullable self FK, budget_amount DECIMAL(18,2), planned_start_at, planned_end_at, status, created_by, timestamps.
- `construction_milestones`: id, project_id, work_package_id nullable, title, planned_at, completed_at nullable, status, sort_order, timestamps.
- `construction_progress_updates`: id, project_id, work_package_id nullable, milestone_id nullable, progress_percent DECIMAL(5,2), report_date, notes, visibility (internal/investor), submitted_by, approved_by nullable, approved_at nullable, status, timestamps.
- `labour_entries`: id, project_id, work_package_id nullable, work_date, labour_type, headcount, units DECIMAL(18,3) nullable, rate DECIMAL(18,4) nullable, total_amount DECIMAL(18,2), notes, status, created_by, approved_by nullable, timestamps. Approved labour entry emits one cost event; do not duplicate expense.
- `project_cost_allocations`: id, project_id, source_type, source_id, cost_category, amount DECIMAL(18,2), allocation_scope (direct/shared), effective_at, reversal_of_id nullable, posted_at, timestamps; unique source event + allocation type to prevent duplicates. The Phase 3/4 flat allocation engine will use saleable-area weights; do not prematurely allocate to unsold flats here.

### 4.2 Materials and stock
- `material_categories`: id, parent_id nullable, name, code unique, timestamps.
- `material_units`: id, code unique, name, precision, timestamps.
- `materials`: id, sku unique, name, category_id FK, base_unit_id FK, reorder_level DECIMAL(18,3), is_active, timestamps, softDeletes.
- `warehouses`: id, code unique, name, project_id nullable FK (null = company warehouse), address nullable, is_active, timestamps.
- `stock_lots` (optional valuation support): if using WAC only, use transactional balance snapshots instead of FIFO lots.
- `stock_balances`: id, warehouse_id, material_id, owned_qty DECIMAL(18,3), custody_borrowed_in_qty DECIMAL(18,3), custody_borrowed_out_qty DECIMAL(18,3), avg_unit_cost DECIMAL(18,4), version, timestamps; UNIQUE(warehouse_id, material_id). Treat as locked cache, reconcile to movement ledger.
- `stock_movements`: id, movement_no unique, material_id, from_warehouse_id nullable, to_warehouse_id nullable, movement_type (purchase_receipt/issue/borrow_issue/borrow_receipt/return_issue/return_receipt/transfer/adjustment/reversal), quantity DECIMAL(18,3), unit_cost DECIMAL(18,4), total_value DECIMAL(18,2), ownership_from_project_id nullable, ownership_to_project_id nullable, reference_type, reference_id, reversal_of_id nullable, posted_at, posted_by, idempotency_key unique, timestamps; index (material_id, posted_at), (reference_type, reference_id), warehouse/date.
- `material_borrows`: id, borrow_no unique, source_project_id FK, destination_project_id FK, source_warehouse_id FK, destination_warehouse_id FK, status (draft/submitted/approved/issued/partially_returned/returned/partially_converted/converted/closed/rejected), requested_by, approved_by nullable, approved_at nullable, notes, timestamps.
- `material_borrow_lines`: id, borrow_id FK, material_id FK, requested_qty, approved_qty nullable, issued_qty default 0, returned_qty default 0, converted_qty default 0, locked_unit_cost nullable, timestamps. Constraint at service level: returned_qty + converted_qty <= issued_qty.
- `material_borrow_actions`: id, borrow_id, action, actor_id, reason nullable, metadata JSON, created_at; append-only.
- `material_transfers`: id, transfer_no unique, source_project_id, destination_project_id, source_warehouse_id, destination_warehouse_id, borrow_id nullable, status, requested_by, approved_by nullable, approved_at nullable, posted_at nullable, reason, timestamps.
- `material_transfer_lines`: id, transfer_id, material_id, quantity, unit_cost, total_value, borrow_line_id nullable, timestamps. If borrow conversion, only outstanding borrowed quantity can convert.
- `stock_adjustments`: id, warehouse_id, reason, status, requested_by, approved_by nullable, posted_at nullable, timestamps.
- `stock_adjustment_lines`: id, adjustment_id, material_id, qty_delta signed DECIMAL(18,3), unit_cost, valuation_delta signed DECIMAL(18,2), timestamps.

**Critical invariants**
1. Borrow does not silently move project cost ownership.
2. Conversion/transfer changes project cost attribution exactly once; source negative and destination positive, equal magnitude.
3. Physical custody and ownership remain distinguishable after borrow.
4. No transfer of unowned or already returned borrowed quantity.
5. All stock/cost posting operations are atomic and idempotent.

### 4.3 Suppliers and purchasing
- `suppliers`: id, supplier_code unique, name, contact_person nullable, mobile nullable, email nullable, address nullable, tax_id nullable, status, opening_balance DECIMAL(18,2) default 0 with approved import source, timestamps, softDeletes.
- `purchase_orders`: id, po_no unique, supplier_id, project_id nullable, warehouse_id, order_date, expected_date nullable, status, subtotal, discount_amount, tax_amount, total_amount, notes, created_by, approved_by nullable, approved_at nullable, timestamps.
- `purchase_order_lines`: id, purchase_order_id, material_id, quantity, unit_price, discount_amount, tax_amount, line_total, received_qty default 0, timestamps.
- `goods_receipts`: id, grn_no unique, purchase_order_id, warehouse_id, received_at, status, received_by, posted_at nullable, timestamps.
- `goods_receipt_lines`: id, goods_receipt_id, purchase_order_line_id, material_id, received_qty, accepted_qty, rejected_qty, unit_cost, timestamps.
- `supplier_bills`: id, bill_no unique, supplier_id, purchase_order_id nullable, supplier_invoice_no nullable, invoice_date, due_date nullable, subtotal, tax_amount, total_amount, status, approved_by nullable, posted_at nullable, timestamps.
- `supplier_bill_lines`: id, supplier_bill_id, goods_receipt_line_id nullable, description, amount, timestamps.
- `supplier_payments`: id, payment_no unique, supplier_id, payment_date, amount, payment_method, payment_reference nullable, status, approved_by nullable, posted_at nullable, reversal_of_id nullable, timestamps.
- `supplier_payment_allocations`: id, payment_id, supplier_bill_id, amount, timestamps; total allocations <= payment amount, total per bill <= outstanding.
- `purchase_returns`: id, return_no unique, supplier_id, goods_receipt_id, status, reason, approved_by nullable, posted_at nullable, timestamps.
- `purchase_return_lines`: id, purchase_return_id, goods_receipt_line_id, material_id, quantity, unit_cost, timestamps.
- `supplier_ledger_entries`: id, supplier_id, source_type, source_id, entry_date, charge_amount default 0, payment_amount default 0, reversal_of_id nullable, posted_at, timestamps; UNIQUE(source_type, source_id, supplier_id, entry kind). Running balance computed chronologically.

### 4.4 Contractors
- `contractors`: id, contractor_code unique, name, contact_person, mobile nullable, email nullable, address nullable, status, timestamps, softDeletes.
- `contractor_work_orders`: id, wo_no unique, contractor_id, project_id, work_package_id nullable, title, scope, contract_amount, start_date nullable, end_date nullable, status, created_by, approved_by nullable, approved_at nullable, timestamps.
- `contractor_measurements`: id, work_order_id, measurement_date, description, quantity, unit, rate, measured_amount, status, submitted_by, approved_by nullable, timestamps.
- `contractor_bills`: id, bill_no unique, work_order_id, contractor_id, project_id, bill_date, gross_amount, advance_adjustment default 0, retention_amount default 0, other_deductions default 0, net_payable, status, approved_by nullable, posted_at nullable, timestamps.
- `contractor_bill_measurements`: id, contractor_bill_id, measurement_id, amount; UNIQUE(measurement_id) for no double billing unless explicit partial-billing design.
- `contractor_payments`: id, payment_no unique, contractor_id, bill_id nullable, project_id, payment_date, amount, payment_method, reference nullable, status, approved_by nullable, posted_at nullable, reversal_of_id nullable, timestamps.
- `contractor_ledger_entries`: id, contractor_id, source_type, source_id, entry_date, charge_amount default 0, payment_amount default 0, posted_at, reversal_of_id nullable, timestamps; UNIQUE appropriate source keys.

### 4.5 Expenses
- `expense_categories`: id, parent_id nullable, code unique, name, allowed_scope (general/project/both), active, timestamps.
- `expenses`: id, expense_no unique, scope (general/project), project_id nullable, work_package_id nullable, category_id, expense_date, description, payee_name nullable, amount, payment_method nullable, payment_reference nullable, status, created_by, submitted_by nullable, approved_by nullable, approved_at nullable, paid_at nullable, reversal_of_id nullable, timestamps.
- `expense_attachments`: id, expense_id, file_id FK to Phase 1 file storage, timestamps (only if generic polymorphic attachments are unavailable).
- `expense_approval_actions`: id, expense_id, actor_id, action, reason nullable, created_at; append-only.

**Important accounting-ready bridge**
- Reuse Phase 1 financial event/outbox tables if already defined. If not, add `business_financial_events`.
- For every approved/posted event record balanced **cost attribution changes** and future GL mapping metadata; do not pretend Phase 5 GL exists.
- Explicitly document posting moments: PO approval = commitment only; GRN = inventory recognition; supplier bill = payable; payment = settlement; work-order approval = commitment; contractor bill = payable/cost as applicable; expense approval = approved obligation, payment = cash settlement. Final GAAP/tax recognition policies remain Phase 5 decisions.
- Supplier/contractor ledgers are operational subledgers; later reconcile to General Ledger.
- Avoid double counting supplier bill and received inventory, contractor bill and expense, stock issue and project cost.

## 5. Relationship map and indexes

- Project 1:N work packages, milestones, progress updates, labour entries, warehouses, expenses, work orders.
- Warehouse N:1 project (nullable), warehouse+material 1:1 stock balance, warehouse/material 1:N movements.
- Borrow source/destination each references project and warehouse; borrow 1:N lines; conversion transfer may reference borrow and line.
- Supplier 1:N POs, bills, payments, ledger entries; PO 1:N lines and receipts.
- Contractor 1:N work orders, bills, payments, ledger entries.
- Expense category 1:N expenses; project expense must reference a valid project.
- All financial events reference their source documents by stable IDs.
- Index status+project+date, supplier/date, contractor/date, material/warehouse, approval queue/status, posted_at and idempotency keys.
- Enforce FKs and CHECKs supported by MySQL 8; service-level checks for cross-row sums and transitions.

## 6. Permissions — recommended seeds

`construction.view`, `.create`, `.update`, `.progress.submit`, `.progress.approve`, `.labour.manage`, `.cost.view`, `.cost.export`
`materials.view`, `.manage`, `.receive`, `.issue`, `.adjust.request`, `.adjust.approve`, `.borrow.request`, `.borrow.approve`, `.borrow.issue`, `.borrow.receive`, `.borrow.return`, `.borrow.convert`, `.transfer.request`, `.transfer.approve`, `.transfer.post`, `.movement.reverse`, `.valuation.view`
`suppliers.view`, `.create`, `.update`, `.ledger.view`, `.ledger.export`
`purchases.view`, `.create`, `.submit`, `.approve`, `.receive`, `.bill.manage`, `.payment.create`, `.payment.approve`, `.return`
`contractors.view`, `.create`, `.update`, `.workorder.create`, `.workorder.approve`, `.measurement.manage`, `.bill.create`, `.bill.approve`, `.payment.create`, `.payment.approve`, `.ledger.view`
`expenses.view.general`, `.view.project`, `.create.general`, `.create.project`, `.submit`, `.approve`, `.pay`, `.reverse`, `.export`
`financial.events.view`, `financial.events.reconcile`

These are **suggested permission identifiers**; integrate with Phase 1 permission namespace. Every operation also checks project membership/scope and record ownership. Never give investor/customer roles these internal permissions by default. Separate submitter and approver where possible; enforce self-approval restrictions as a configurable policy.

## 7. API/AJAX endpoint blueprint

Prefix authenticated internal routes under the existing ERP namespace. Return consistent JSON `{success, message, data, errors, meta}` and correct HTTP codes. Use policy checks and scoped queries. Return 409 for stale versions, insufficient stock, already posted, over-return, duplicate action or mismatched state.

## 8. Acceptance tests and scenarios

**Stock and costing**
1. Receive 100 bags at 500 and 100 bags at 600 => WAC 550 per bag, subject to rounding policy.
2. Borrow 20 from Project A to B: physical custody moves, outstanding borrowed = 20, no permanent project cost reallocation.
3. Return 8: outstanding 12.
4. Convert 7 to permanent transfer: outstanding 5; source cost decreases 7 x locked unit cost; destination increases same; linked movement history preserved.
5. Attempt to convert 6 more => 409, no partial side effects.
6. Two simultaneous requests for last 5 units => only one may post; stock never negative.
7. Reversal restores attribution through compensating entries; original movement remains visible.
8. Inventory reconciliation from ledger equals snapshots.

**Supplier/contractor**
9. Partial PO receipt and return update stock correctly; no duplicate posting on retry.
10. Supplier bill 100, payment 40 => outstanding 60; payment allocation cannot exceed due.
11. Contractor bill 1000, advance adjustment 200, retention 50 => net payable 750; payment and outstanding correct.
12. Activity and operational ledger link to source docs; cannot silently edit posted bill.

**Expenses and security**
13. General expense has null project_id; project expense requires project_id.
14. Unauthorized user cannot approve or view out-of-scope project even with guessed URL.
15. AJAX actions show inline validation and refresh row, counters and ledger without full reload.
16. Searchable dropdown for >7 options; remote search does not dump all supplier/material records.
17. Investor-safe progress data never leaks internal financial fields before permission rules are applied.
18. Accounting-ready events are unique, traceable, reversible and do not duplicate costs.

## 9. Delivery order and checkpoints

1. Review Phase 1 implementation and generate a gap report before writing code.
2. Confirm schema, statuses, valuation/custody invariants and permission mapping.
3. Implement shared Phase 2 UI components and service contracts.
4. Implement construction baseline, materials/warehouse, supplier/purchase, contractor, expense modules.
5. Add cross-module financial event bridge, ledger reconciliation, dashboards and tests.
6. Run migrations on a clean DB and upgrade DB; no destructive migration without backup.
7. Demo core flows using realistic sample data; deliver known limitations and Phase 3/5 integration contract.

## 10. Sequential prompts

1. UI foundation + Phase 2 shell.
2. Database migrations + permissions.
3. Construction Management.
4. Material & Warehouse Management.
5. Supplier & Purchase Management.
6. Contractor Management.
7. Expense Management.
8. Integration, reports, security and release gate.

(Full prompt text was provided with the specification; the implemented interpretation is in `docs/decisions/phase-2.md`.)

## 12. Remaining policy decisions (not blockers for initial scaffold)

- Whether a material borrower may consume material before ownership conversion; recommended: require conversion or an explicit approved consumption-cost rule.
- Stock valuation rounding and historical cost adjustment method; recommended: four-decimal unit cost and two-decimal posted totals, adjustment entries for rounding.
- Who may approve own request; recommended: disallow self-approval for financial/stock posting.
- Whether general expenses may be allocated to projects later; recommended: support future approved allocation, do not automatically allocate.
- Tax/VAT/withholding specifics, financial year close and full journal mappings belong to Phase 5 accounting design.

**Definition of Done:** Phase 2 five modules work end-to-end, with consistent premium UI, responsive AJAX flows, searchable selectors, role/project authorization, immutable stock and ledger history, cost-safe cross-project transfers, reproducible tests and a clean accounting-ready integration layer.
