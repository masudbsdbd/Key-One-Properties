# Phase 2 decisions and plan

## A. Phase 1 compatibility report

| Phase 2 needs | Phase 1 has | Plan |
| --- | --- | --- |
| Module layout | `app/Modules/{Core,Projects,Flats,Customers}` with Controllers, Requests, Policies, Services; route files per module | New modules: `Construction`, `Inventory`, `Purchasing`, `Contractors`, `Expenses`, each with its own `routes/<module>.php` |
| Routes | Root paths (`/projects`, `/units`), not `/erp/...` | Keep root paths for consistency (`/inventory/...`, `/purchase-orders`, ...) |
| IDs | `public_id` (16 random chars) on every exposed table, route binding by `public_id` | Same for every Phase 2 document and master record |
| Projects, stages, progress | `projects`, `project_stages`, immutable `project_progress_updates` (internal/investor visibility) | Work packages may link to a stage; construction progress is per work package; stage progress stays as is |
| Project access | `Project::visibleTo()` / `isVisibleTo()` (membership or `projects.view_all`) | Every Phase 2 query and policy uses it; company-level records (general expenses, company warehouse) use their own permissions |
| Permissions | Spatie, names `module.action`, catalogue in `App\Support\Permissions`, Admin role always gets all | Add Phase 2 groups to the catalogue; Super Admin bypass applies |
| Audit | Spatie activitylog with `project_id` column, `ActivityPresenter` | Same; documents log with project context |
| AJAX contract | `Ajax::success/error/table/options`, remote modal, data tables, partial reloads, action buttons | Reused for every Phase 2 screen |
| Files | Per-module media/document tables on the private disk; no generic file table | Add one polymorphic `attachments` table for Phase 2 documents (progress evidence, bills, expenses, supplier and contractor files) |
| Financial events | `financial_events` outbox, idempotency key, immutable business columns, type catalogue | Reuse. Add `reversal_of_id`, add Phase 2 event types |
| Document numbers | Ad-hoc (`PRJ-0001`, `CUS-00001`) | Add `document_sequences` with row-locked counters (`PO-2026-00001`) |
| Approval foundation | `approval_requests` (unused) | Phase 2 documents carry their own status, `approved_by`, `approved_at` and an append-only action table; `approval_requests` stays for generic future use |

No Phase 1 table needs a destructive change. All Phase 2 migrations are additive.

## B. Migration plan (in order)

1. Shared: `document_sequences`, `attachments`, `project_cost_allocations`, `financial_events.reversal_of_id`.
2. Construction: `construction_work_packages`, `construction_milestones`, `construction_progress_updates`, `labour_entries`.
3. Inventory: `material_categories`, `material_units`, `materials`, `warehouses`, `stock_balances`, `stock_movements`, `stock_receipts` (+ lines), `stock_issues` (+ lines), `stock_adjustments` (+ lines), `material_borrows` (+ lines, actions), `material_transfers` (+ lines).
4. Purchasing: `suppliers`, `purchase_orders` (+ lines), `goods_receipts` (+ lines), `supplier_bills` (+ lines), `supplier_payments` (+ allocations), `purchase_returns` (+ lines), `supplier_ledger_entries`.
5. Contractors: `contractors`, `contractor_work_orders`, `contractor_measurements`, `contractor_bills`, `contractor_bill_measurements`, `contractor_payments`, `contractor_ledger_entries`.
6. Expenses: `expense_categories`, `expenses`, `expense_actions`.

`stock_lots` is not created: valuation is weighted average (WAC), so balances plus the movement ledger are enough.

## C. Dependency graph

```
shared (sequences, attachments, cost allocations, events, permissions)
  ├── construction (work packages, progress, labour) ──┐
  ├── inventory (materials, warehouses, stock ledger) ──┤
  │     └── purchasing (GRN and returns post through inventory services)
  ├── contractors (work orders may link to work packages)
  └── expenses (project expenses may link to work packages)
                                                        └── integration (reconciliation, dashboards, reports)
```

## D. Decisions (recommended defaults adopted)

### Valuation and custody

- Weighted average cost per warehouse and material. Unit costs `DECIMAL(18,4)`, values `DECIMAL(18,2)`.
  Arithmetic uses `Brick\Math\BigDecimal` (already a Laravel dependency); never floats.
- A warehouse belongs to one project or to the company (`project_id` null). Owned stock in a warehouse is owned by that project (or the company).
- `stock_balances` per warehouse and material keeps:
  - `owned_qty`: owned and physically here,
  - `borrowed_in_qty`: physically here, owned by another project,
  - `borrowed_out_qty`: owned by this warehouse's project, physically lent elsewhere,
  - `owned_value`: value of everything this warehouse owns (`owned_qty + borrowed_out_qty`),
  - `avg_unit_cost = owned_value / (owned_qty + borrowed_out_qty)`.
- Physical qty = `owned_qty + borrowed_in_qty`. Available to issue = `owned_qty`.
- **Borrowed stock cannot be issued or transferred by the borrower** until it is converted (blueprint policy 1).
- Movement effects:

| Movement | Source warehouse | Destination warehouse |
| --- | --- | --- |
| receipt (purchase or opening) | | owned +q, value +v |
| purchase return | owned −q, value −v | |
| issue (consumption) | owned −q, value −v | |
| adjustment (signed) | owned ±q, value ±v | |
| borrow issue | owned −q, borrowed_out +q | borrowed_in +q |
| borrow return | owned +q, borrowed_out −q | borrowed_in −q |
| borrow conversion | borrowed_out −q, value −v | borrowed_in −q, owned +q, value +v |
| direct transfer | owned −q, value −v | owned +q, value +v |
| reversal | exact inverse of the original | |

- Borrow lines freeze `locked_unit_cost` at issue time. Conversion moves exactly `q × locked_unit_cost` out of the source and into the destination (equal magnitude, invariant 2).
- Issuing the last owned unit takes the remaining value so no rounding residue is left.
- Each posting locks the affected `stock_balances` rows (`SELECT … FOR UPDATE`, ordered by id), writes immutable `stock_movements` with a unique idempotency key, then updates the balances. Reconciliation replays movements and compares them with the balances.

### Project cost attribution

`project_cost_allocations` is the single source of project cost. Categories:

| Category | Created when | Amount |
| --- | --- | --- |
| material | stock issued from a project warehouse (or a company warehouse to a project) | issue value |
| labour | labour entry approved | entry total |
| contractor | contractor bill approved | gross − other deductions (retention is still cost) |
| expense | project expense approved | expense amount |

Reversals add a negative row linked by `reversal_of_id`; nothing is deleted. Each row has a unique idempotency key.

Inventory still on hand is not cost yet. The project report shows **material consumed** (cost) and **material in stock** (inventory value owned by the project's warehouses). A permanent transfer or conversion moves inventory value from the source project's warehouse to the destination's, so the source's attributed material decreases and the destination's increases by the same amount, exactly once.

No double counting:
- GRN = inventory, not cost. Supplier bill = payable, not cost. Cost arises when material is issued.
- Contractor bill = cost (on approval) and payable. Contractor payment = settlement only.
- Expense approval = cost (project scope only). Payment = settlement only.
- General expenses never reach project cost (no automatic allocation).
- Expense categories cannot be used for materials or contractor work; those go through their modules.

### Workflows

| Document | Statuses |
| --- | --- |
| Purchase order | draft → submitted → approved → partially_received → received → closed; rejected / cancelled |
| Goods receipt | posted on save (inventory recognition) |
| Supplier bill | draft → approved (posts ledger charge); reversed |
| Supplier payment | pending → approved (posts ledger payment); reversed |
| Purchase return | draft → posted (stock out + ledger credit) |
| Borrow | draft → submitted → approved → issued → partially_returned / returned / partially_converted / converted → closed; rejected / cancelled |
| Transfer | draft → submitted → approved → posted; rejected / cancelled; reversible |
| Stock adjustment | draft → submitted → approved → posted; rejected |
| Work order | draft → approved → completed / closed; cancelled |
| Measurement | draft → approved (→ billed) |
| Contractor bill | draft → approved (posts ledger + cost); reversed |
| Contractor payment | pending → approved (posts ledger); reversed |
| Expense | draft → submitted → approved → paid; rejected; reversed |
| Construction progress | submitted → approved (published if visibility is investor); rejected |
| Labour entry | draft → approved (posts cost); rejected; reversed |

- **Self-approval is not allowed** for stock, financial and cost postings (`config('keyone.allow_self_approval')`, default false).
- Rejections and reversals require a reason.
- Posted records are never edited or deleted; corrections are reversals.

### Ledgers

- Supplier ledger: bill approved = charge (we owe more); payment approved = payment; purchase return = payment-side credit (debit note); opening balance = charge. Balance = charges − payments.
- Contractor ledger: bill approved = charge of `gross − retention − other deductions`; payment approved = payment. Advance payments (no bill) reduce the balance immediately; a bill's advance adjustment only consumes the advance balance. Example: advance 200, bill gross 1000, retention 50 → charge 950, balance 750 = net payable.
- Retention is tracked per bill and released by a separate retention-release bill line on final settlement.
- Both ledgers are operational subledgers, not the general ledger.

### Financial events

Reuse `financial_events`. New types: `material.received`, `material.consumed`, `stock.adjusted`, `purchase.returned`, `supplier.bill_approved`, `labour.approved`, `contractor.retention_released`, `expense.approved`, `expense.paid`, plus the existing Phase 2 types. Reversals record a new event with `reversal_of_id`.

### Other

- Document numbers: `PREFIX-YYYY-NNNNN` from `document_sequences` (row-locked).
- Attachments: images, PDF, Office files up to 10 MB on the private disk, authorised through the parent record.
- Exports: CSV only, permission-gated.
- The investor portal is not built; only `visibility` / `published_at` fields and investor-safe query scopes are provided.

## E. Implementation checklist

- [ ] Shared foundation (sequences, attachments, cost allocations, events, permissions, navigation)
- [ ] Construction
- [ ] Inventory (WAC ledger, borrow, return, convert, transfer, adjustment, reconciliation)
- [ ] Purchasing (PO, GRN, bill, payment, return, ledger)
- [ ] Contractors (work order, measurement, bill, payment, ledger)
- [ ] Expenses (general and project, approval, payment, reversal, reports)
- [ ] Integration (reconciliation command, dashboards, exports, security sweep, docs)

## F. Assumptions and open questions

None block the build; each uses the recommended default above and can be changed later.

1. Borrowed material cannot be consumed before conversion.
2. Self-approval is disabled for postings.
3. General expenses are never allocated to projects automatically.
4. Contractor retention defaults to 0% per work order.
5. Tax / VAT / withholding is captured as plain amounts only; rules belong to Phase 5.
