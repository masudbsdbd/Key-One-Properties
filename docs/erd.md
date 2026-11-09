# Phase 1 ERD

```mermaid
erDiagram
    users ||--o{ model_has_roles : has
    roles ||--o{ model_has_roles : grants
    roles ||--o{ role_has_permissions : has
    permissions ||--o{ role_has_permissions : in
    users }o--o{ departments : department_user
    users }o--o{ projects : project_user
    users ||--o{ projects : creates

    projects ||--o{ project_stages : has
    projects ||--o{ project_progress_updates : has
    project_stages ||--o{ project_progress_updates : reports
    projects ||--o{ project_media : gallery
    projects ||--o| project_media : cover

    projects ||--o{ buildings : has
    buildings ||--o{ floors : has
    floors ||--o{ flats : has
    buildings ||--o{ flats : has
    projects ||--o{ flats : scopes
    flats ||--o{ flat_price_history : prices
    flats ||--o{ flat_status_history : statuses
    flats ||--o{ flat_media : images

    customers ||--o{ customer_addresses : has
    customers ||--o{ customer_notes : has
    customers ||--o{ customer_documents : has
    users ||--o{ customers : creates

    projects ||--o{ financial_events : references
    flats ||--o{ financial_events : references
    customers ||--o{ financial_events : references
    users ||--o{ approval_requests : requests
```

Notes:

- Every table exposed to the browser has a random 16-character `public_id`; internal `id`
  values never leave the server.
- `flats.project_id` is denormalised for scoping and always derived from the floor's building.
- There is no customer–flat link in Phase 1; bookings in Phase 3 will create it.
- `activity_log.project_id` lets a project's history be listed in one indexed query.
- `settings` (key/value) holds branding.

# Phase 2 ERD

```mermaid
erDiagram
    projects ||--o{ construction_work_packages : has
    construction_work_packages ||--o{ milestones : has
    projects ||--o{ construction_progress_updates : has
    projects ||--o{ labour_entries : has

    projects ||--o{ warehouses : owns
    warehouses ||--o{ stock_balances : holds
    materials ||--o{ stock_balances : in
    warehouses ||--o{ stock_movements : ledger
    stock_receipts ||--o{ stock_movements : posts
    stock_issues ||--o{ stock_movements : posts
    material_borrows ||--o{ material_borrow_lines : has
    material_transfers ||--o{ material_transfer_lines : has

    suppliers ||--o{ purchase_orders : receives
    purchase_orders ||--o{ purchase_order_lines : has
    purchase_orders ||--o{ goods_receipts : received_by
    goods_receipts ||--o{ goods_receipt_lines : has
    goods_receipt_lines }o--o| supplier_bills : billed_in
    suppliers ||--o{ supplier_bills : owes
    suppliers ||--o{ supplier_payments : paid
    supplier_payments ||--o{ supplier_payment_allocations : allocates
    supplier_bills ||--o{ supplier_payment_allocations : settled_by
    goods_receipts ||--o{ purchase_returns : returned
    suppliers ||--o{ supplier_ledger_entries : ledger

    contractors ||--o{ contractor_work_orders : awarded
    projects ||--o{ contractor_work_orders : scopes
    contractor_work_orders ||--o{ contractor_measurements : measured
    contractor_work_orders ||--o{ contractor_bills : billed
    contractor_bills ||--o{ contractor_bill_measurements : includes
    contractor_measurements ||--o| contractor_bill_measurements : billed_once
    contractor_work_orders ||--o{ contractor_payments : paid
    contractor_bills ||--o{ contractor_payments : settles
    contractors ||--o{ contractor_ledger_entries : ledger

    expense_categories ||--o{ expense_categories : parent
    expense_categories ||--o{ expenses : classifies
    projects ||--o{ expenses : project_scope
    expenses ||--o{ expense_actions : history

    projects ||--o{ project_cost_allocations : cost
    projects ||--o{ financial_events : references
```

Notes:

- `project_cost_allocations` has one writer (`CostAttribution`): stock issues (material), labour entries,
  contractor bills and project expenses. Reversals add negative rows; nothing is deleted.
- Stock movements, ledger entries, borrow actions and expense actions are append-only.
- `attachments` (polymorphic) holds files for progress updates, suppliers, orders, bills, payments,
  contractors, work orders and expenses.

# Phase 3 ERD

```mermaid
erDiagram
    flats ||--o| active_flat_claims : claimed_by
    flats ||--o{ flat_bookings : booked
    flats ||--o{ flat_sales : sold
    flats ||--o{ flat_price_revisions : price_requests
    customers ||--o{ flat_bookings : books
    customers ||--o{ flat_sales : buys
    flat_bookings ||--o| flat_sales : converts
    flat_sales ||--|| sale_agreements : agreement
    flat_sales ||--|| flat_registrations : registration
    flat_sales ||--|| flat_handovers : handover
    flat_sales ||--o{ sales_cancellations : cancels
    flat_bookings ||--o{ sales_cancellations : cancels
    customers ||--o{ customer_refunds : refunded

    flat_sales ||--o{ installment_plans : plans
    installment_plans ||--o{ installment_schedule_items : items
    installment_plans ||--o{ installment_reschedules : reschedules
    customers ||--o{ customer_payments : pays
    customer_payments ||--o{ payment_allocations : applied_to
    installment_schedule_items ||--o{ payment_allocations : receives
    customer_payments ||--o| money_receipts : receipt
    customers ||--o{ customer_late_fee_policies : policy
    installment_schedule_items ||--o{ late_fee_assessments : fees
    customers ||--o{ customer_statement_entries : statement

    investors ||--o{ investor_flat_agreements : agreements
    flats ||--o{ investor_flat_agreements : shares
    investor_flat_agreements ||--o| investor_flat_agreements : supersedes
    investor_flat_agreements ||--o{ investor_capital_transactions : capital
    projects ||--o{ flat_cost_allocation_runs : runs
    flat_cost_allocation_runs ||--o{ flat_cost_allocations : allocations
    flats ||--o{ flat_profit_calculations : profit
    flat_cost_allocation_runs ||--o{ flat_profit_calculations : uses
    flat_profit_calculations ||--o{ investor_profit_allocations : splits
    investors ||--o{ investor_profit_distributions : distributions
    investor_profit_distributions ||--o{ investor_payouts : payouts
    investors ||--o{ investor_ledger_entries : ledger
```

Notes:

- `active_flat_claims.flat_id` is unique: the database decides which booking or sale holds a unit.
- `customer_statement_entries`, `investor_ledger_entries`, `payment_allocations` and `late_fee_assessments`
  are append-only; reversals add rows linked by `reversal_of_id`.
- `project_cost_allocations` and `expenses` gained a nullable `flat_id` (direct unit cost);
  `users` gained `preferences` (JSON, whitelisted keys).

# Phase 4 ERD

```mermaid
erDiagram
    users ||--o| investor_user_links : "portal account"
    investors ||--o{ investor_user_links : accounts
    users ||--o| customer_user_links : "portal account"
    customers ||--o{ customer_user_links : accounts
    users ||--o{ portal_access_grants : grants
    portal_access_grants }o--|| projects : "resource (project)"
    portal_access_grants }o--|| flats : "resource (flat)"
    users ||--o{ portal_access_audits : audits
    users ||--o{ report_runs : requests
    report_runs ||--o| report_files : file
```

Notes:

- `users.account_type` is `staff`, `investor` or `customer`; portal accounts never pass the `staff` middleware.
- A link is usable only when active, verified by a second user and not revoked. Revoking deletes the user's sessions.
- `portal_access_grants` add one capability on one project or flat, optionally until an expiry date
  (polymorphic `resource_type`/`resource_id`).
- `portal_access_audits` record portal sign-ins, downloads, exports and denied attempts.
- `attachments` gained `portal_visible`; only flagged files of the investor's own agreements and capital entries
  are shown in the portal.
- `report_runs` keep the requester, report key, filters, status, row count and expiry; `report_files` point to
  a private file on the `local` disk. Report definitions live in code (`ReportRegistry`), not in a table.
