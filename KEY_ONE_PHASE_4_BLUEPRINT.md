# KEY ONE PROPERTIES ERP — PHASE 4 BLUEPRINT

**Version:** 1.0 · **Scope:** Investor Portal, Customer Portal, Dashboard & Reports · **Status:** Development blueprint; not a claim of implemented functionality.

## 1. Master requirements and boundaries

Continue the **existing Phase 1–3 Laravel application and MySQL database**. Do not scaffold a separate project. Implement three logical modules:

- **13 — Investor Portal**: investor-specific investments, flat/project progress, cost and profit visibility controlled by granular permissions.
- **14 — Customer Portal**: read-only own flat, payment history, installment schedule, paid/due, next payment, receipts and statements. No online payments or requests in Phase 4.
- **15 — Dashboard & Reports**: internal management dashboards and permission-filtered operational reports across existing modules.

**Confirmed rules:** Same-domain paths `/admin`, `/investor`, `/customer`; separate layouts, routes, guards/policies and menus while sharing a single Laravel application and database. One flat may have multiple investors, total active investor profit shares must not exceed 100%, investor profit distributions require permissioned approval, and shared construction costs are allocated to flats by **saleable area**. General expenses must not automatically be charged to projects. Profit calculation, approval, payable and actual payout are separate steps. Customer portal is read-only. Accounting module's complete double-entry GL is Phase 5, so Phase 4 financial reports are operational/provisional and must not be represented as audited statements.

**Mandatory UI:** premium clean light-mode design consistent with Phase 1; Laravel Blade + Tailwind CSS + jQuery/AJAX + Select2. For >7 dropdown choices use searchable selection; for large lists use remote AJAX lookup, debounce and server-side pagination. Use no-full-reload filtering, pagination, detail drawers, exports initiation, and refresh where appropriate. Do not introduce React, Vue or Livewire.

## 2. Phase 1–3 integration audit before coding

Inspect the actual models, migrations, routes, permissions, roles, investor-flat relationships, investment/capital ledgers, flat cost allocation, sales, receipts, installments, projects, progress, files, financial events and report query conventions. Produce a compatibility matrix. Do **not** invent duplicate tables when equivalent ones exist. Identify incomplete Phase 3 profit realization and payout rules; do not silently decide them. Respect Phase 1 permission namespaces and existing UI tokens.

## 3. Module 13 — Investor Portal

### Pages

1. **Overview:** total invested capital, active investments, paid-out profit, approved-but-unpaid profit, provisional estimated profit (clearly labeled), project progress cards, recent transactions.
2. **My Investments:** filterable table of investments and participation agreements; project, flat, capital, agreed share, investment status, dates.
3. **Investment Detail:** linked flat, agreement, capital movements, cost/profit breakdown, approval/payout timeline, downloadable authorized documents.
4. **Project Progress:** stages, milestones, percentage progress, approved investor-visible updates, approved photos and timeline.
5. **Project Financials:** construction material, labour, contractor, project expenses and shared costs only for project/fields explicitly authorized to that investor.
6. **Flat Financials:** allocated direct/shared costs, sale proceeds, profit calculation snapshots, investor share, approved payable, paid amount; hide fields not authorized.
7. **Statements & Documents:** investor ledger, capital deposits/withdrawals (if implemented), profit distribution statements, permissioned exports.
8. **Profile & Security:** personal details, password/session security as supported by Phase 1.

### Visibility rules

- Identify current investor from authenticated account via **verified, active** `user ↔ investor` link; never trust investor_id from the browser.
- Investor must have an active/authorized investment association for the flat/project, **plus** field-level permission for the requested cost/profit category.
- No project-wide visibility merely because the investor owns one flat. Require explicit project-level grant for aggregate project costs; flat-only grant is limited to their flat and approved contextual progress.
- Separate permission capabilities: `investor.portal.view`, `investor.portal.investments.view`, `investor.portal.project.progress.view`, `investor.portal.project.financials.view`, `investor.portal.flat.financials.view`, `investor.portal.cost.material.view`, `.labour.view`, `.contractor.view`, `.expense.view`, `investor.portal.profit.view`, `investor.portal.documents.download`, `investor.portal.statements.export`.
- Enforce scope and field permissions **in service/query/serializer** before returning JSON, HTML, CSV, Excel or PDF; hiding a UI component is insufficient.
- Do not expose draft/rejected progress updates, internal comments, supplier/contractor PII, other investors' identity or personal capital without specific business permission.
- Provisional profit is **not** approved profit or payable. Show calculation basis, as-of date and any unconfirmed assumptions.

## 4. Module 14 — Customer Portal

### Pages

1. **Overview:** own flat(s), project and unit status, total contract amount, collected, due, next installment, recent receipts.
2. **My Flats:** unit information, building/floor, area, agreement/registration/handover status as available and authorized.
3. **Installment Schedule:** original and current schedule, due dates, amount, paid, remaining, overdue, optional late fee, advance credit and reschedule history where customer-visible.
4. **Payment History:** receipts, partial payments, advance payments, refund adjustments, allocations and payment references.
5. **Statements & Receipts:** download signed/authorized receipts and own statements only.
6. **Profile & Security:** account details, login security. No financial edit or customer self-service request in this phase.

### Access rules

- Derive current customer from a **verified, active** user-to-customer link. One user may have multiple legitimately linked flats; use server-side ownership/authorized relationship checks on every record.
- Customers cannot access another customer's receipts, statements, agreements, flat details, files, or even infer existence through API errors.
- Show approved/posted payments only. Canceled/reversed payments are displayed as reversed with traceable adjustments, not silently deleted.
- For pending/unposted late fees, show clear labels; do not invent balances. Compute from the existing Phase 3 allocation ledger, not a separate mutable portal total.
- Read-only means no booking, payment, cancellation, reschedule or refund actions from customer portal.

## 5. Module 15 — Dashboards & Reports

### Admin executive dashboard

- Projects: count by stage, budget vs approved actual costs, progress, milestones and exceptions.
- Flats: available/booked/sold/registered/handed-over status counts, price/value totals.
- Sales: bookings, cancellations, net sales, refunds, price changes.
- Collections: collected, outstanding, overdue, next-due, advances, late fees (distinguish billed vs collected).
- Construction: approved material consumption, labour, contractor costs, general vs project expenses, shared cost allocations.
- Inventory: current owned stock, borrowed-in/out, outstanding borrows, low stock, transfer history.
- Investors: capital received, profit calculation snapshots, pending approvals, approved payables, actual payouts.
- Suppliers/contractors: unpaid bills, aging and payment summaries.
- Activity and exceptions: approval queues, overdue milestones, reconciliation alerts.

### Reports catalog

| Group | Report | Important filters |
|---|---|---|
| Project | Project budget vs actual, stage progress | project, stage, date |
| Construction | Direct/shared cost, work package, labour | project, category, period |
| Inventory | Stock valuation, movements, borrow/return/transfer | warehouse, material, project, period |
| Purchase | PO, GRN, supplier payable/aging | supplier, project, period |
| Contractor | Work orders, running bills, dues | contractor, project, period |
| Expenses | General vs project expenses | scope, category, status, period |
| Sales | Flat status, bookings, cancellations, refunds | project, building, period |
| Collections | Receipts, overdue, advances, installment aging | project, customer, period |
| Investors | Capital, flat-wise cost/profit, approval/payout | project, flat, investor, period |
| Audit | Activity and approval actions | actor, module, action, period |

Reports must declare **basis** (posted/approved/estimated), time window, currency BDT, last updated time and filters. Avoid mixing commitments with expenses, receivables with collections, provisional profit with approved distributions, or owned stock with borrowed custody stock. Do not label operational reports as final GAAP financial statements before Phase 5.

### Export and performance

- Server-side filtering/sorting/pagination for all large tables; no full-table load.
- CSV/Excel/PDF export where existing stack supports it; permission-check each request and each row, log exports. Use background jobs for large exports (database-backed queues/cron acceptable on shared hosting).
- Snapshot/cache expensive aggregate dashboards with permission-safe cache keys and explicit invalidation; do not cache one investor's report for another.
- Charts must have accessible text/table equivalents and should refresh only affected widgets after AJAX changes.

## 6. Proposed database structure (add only when absent)

**Do not duplicate Phase 1–3 master/ledger tables.** Adapt FK types, soft-delete and naming to the actual schema. Monetary values DECIMAL(18,2), percentages DECIMAL(7,4), area DECIMAL(14,3), UTC timestamps.

### Access links and scoped grants

- `investor_user_links`: `id`, `user_id` FK, `investor_id` FK, `is_active`, `verified_at`, `created_by`, `timestamps`; UNIQUE(user_id, investor_id). If Phase 3 already has this link, reuse it.
- `customer_user_links`: `id`, `user_id` FK, `customer_id` FK, `is_active`, `verified_at`, `created_by`, `timestamps`; UNIQUE(user_id, customer_id). If Phase 1 already has it, reuse it.
- `portal_access_grants`: `id`, `user_id` FK, `portal_type` ENUM-like string (`investor`, `customer`), `resource_type` (`project`, `flat`, `investment`, `document`), `resource_id`, `capability` string, `granted_by` FK, `starts_at`, `expires_at` nullable, `revoked_at` nullable, `timestamps`; index(user_id, portal_type, resource_type, resource_id), index(capability, revoked_at). Grants are an **additional restriction**, not a replacement for verified ownership/association. Keep revoke audit.
- `portal_access_audits`: `id`, `user_id`, `portal_type`, `action`, `resource_type`, `resource_id`, `result`, `ip_hash` nullable, `created_at`; index(user_id, created_at). Reuse Phase 1 activity log if adequate.

### Publication, snapshots and reporting

- `progress_publications`: `id`, `progress_update_id` FK, `project_id` FK, `audience` (`investor`, `customer`), `published_by`, `published_at`, `revoked_at` nullable, `visibility_note` nullable, `timestamps`; unique active publication enforced in service or appropriate schema strategy. Customer progress visibility is optional and off by default.
- `report_definitions`: `id`, `key` unique, `name`, `category`, `required_permission`, `filters_schema` JSON, `is_active`, `timestamps`.
- `report_runs`: `id`, `report_definition_id`, `requested_by`, `filter_hash`, `filters_json` JSON, `format`, `status`, `started_at`, `completed_at` nullable, `row_count` nullable, `error_summary` nullable, `expires_at` nullable, `timestamps`; index(requested_by, status, created_at).
- `report_files`: `id`, `report_run_id`, `storage_disk`, `storage_path`, `file_name`, `mime_type`, `size_bytes`, `created_at`; private storage only, signed/authorized download endpoint.
- `dashboard_snapshots` (optional): `id`, `scope_type`, `scope_id`, `metric_key`, `filters_hash`, `as_of_at`, `payload_json`, `expires_at`, `timestamps`; do not store cross-user data in a shared investor cache without permission-aware keys.
- `portal_document_visibility` (optional if generic document ACL missing): `id`, `document_id` FK, `audience`, `resource_type`, `resource_id`, `capability`, `published_by`, `published_at`, `revoked_at`, `timestamps`.

**Existing tables to reuse:** `users`, roles/permissions, `projects`, `buildings`, `floors`, `flats`, `customers`, bookings/sales, installment schedules, payments/allocations, investor records and investment agreements, profit calculation snapshots/approvals/payouts, project cost allocations, construction progress, material movements, supplier/contractor ledgers, file attachments and activity logs.

### Recommended integrity controls

- FKs for user, customer, investor, project, progress and report definition; index resource grants, portal link and report queues.
- Avoid arbitrary polymorphic resource references without checking existence and relationship in service layer.
- Never accept `user_id`, `investor_id`, `customer_id`, `profit_amount`, `paid_total` or scope grants as trusted portal input.
- Revoked links/grants must take effect immediately; invalidate cached dashboard data and private download access.
- Report exports are immutable snapshots with retention/expiry policy; deleting an export must not delete its underlying financial records.

## 7. UI design system and page flows

**Design direction:** clean premium property-management portal with ivory/white canvas, restrained navy typography, one blue/teal accent, generous whitespace, balanced cards, slim sidebar, contextual breadcrumb, subtle borders, consistent iconography and responsive charts. Extend Phase 1 design tokens rather than replacing them.

**Investor layout:** minimal sidebar: Overview, My Investments, Projects, Financials, Statements, Documents, Profile. Header shows portal context, selected investment/project and account menu. Financial figures use clear status chips: Estimated / Calculated / Pending Approval / Approved Payable / Paid.

**Customer layout:** simple navigation: Overview, My Flats, Installments, Payments, Statements, Profile. A prominently displayed next due date and paid/due summary; never confuse total contract amount with remaining balance.

**Admin reports:** filter bar, date presets, project/flat search, KPIs, chart with table view, drill-down drawer, export button, last-refresh indicator. Use stable query parameters and restore filter state on back/forward navigation.

**AJAX rules:**
- >7 options => Select2-like searchable dropdown; >100/large dynamic options => server-side AJAX, debounce ~300 ms, page size 20–25.
- No full reload for filters, table pagination, chart drill-down, investment switcher, portal document lists and status polling/refresh.
- Server-render first meaningful page; AJAX-enhance subsequent interactions. Skeleton states, empty/error/retry, accessible loading announcements.
- Use CSRF, 401/403/409/419/422 handling, abort stale requests, disable duplicate submits, no unsafe optimistic financial updates.
- On mobile: stacked cards, sticky bottom actions where needed, responsive tables or scroll with explicit column priorities.

## 8. Routes and authorization contract

Keep actual existing route prefixes and guard design; illustrative paths:

- `GET /investor/dashboard`
- `GET /investor/investments`
- `GET /investor/investments/{investment}`
- `GET /investor/projects/{project}/progress`
- `GET /investor/projects/{project}/financials`
- `GET /investor/flats/{flat}/financials`
- `GET /investor/statements/{investment}`
- `GET /investor/documents/{document}/download`
- `GET /customer/dashboard`
- `GET /customer/flats`
- `GET /customer/flats/{flat}`
- `GET /customer/installments`
- `GET /customer/payments`
- `GET /customer/receipts/{receipt}/download`
- `GET /admin/dashboard`
- `GET /admin/reports/{reportKey}`
- `POST /admin/reports/{reportKey}/exports`
- `GET /admin/report-runs/{run}/download`

All read routes enforce authentication, active verified link, record association, granular permission and row/field-level scopes. AJAX APIs and exports enforce identical checks. Prefer 404 for inaccessible individual resources when needed to avoid existence disclosure. Rate-limit high-volume exports.

## 9. Data correctness, privacy and financial definitions

- **Customer total paid** = sum of posted collections minus posted reversals/refunds as defined by Phase 3; **due** must use Phase 3 receivable/allocations and agreed contract changes. No frontend math as source of truth.
- **Investor paid profit** = actual posted payouts; **approved payable** = approved distribution minus posted payout; **estimated profit** = explicitly provisional calculation. Never display approved payable as already paid.
- **Flat-wise shared construction cost** = approved shared cost × (flat saleable area / eligible project saleable area), based on Phase 3 allocation snapshot; do not recompute using current flat inventory without versioning.
- **Project costs** = approved/posted material, labour, contractor and project expenses with de-duplication; exclude general expense unless explicitly allocated.
- Reports and dashboards use the same authoritative query services as operational modules; document the as-of semantics and refund/reversal treatment.
- Sensitive downloads use private disk and per-request authorization; no direct public storage URLs.
- Mask contact and financial details in audit logs; enforce reasonable export retention and audit who accessed sensitive reports.

## 10. Testing and acceptance criteria

1. Investor A associated with Flat 101 cannot access Flat 102 financial data without an independent grant/association.
2. Investor A may see Project X progress but not aggregate Project X material/contractor costs unless granted.
3. Investor B's JSON, CSV, PDF, search suggestions and cached metrics do not leak Investor A data.
4. Revoking a grant immediately blocks pages, AJAX APIs and document downloads.
5. Customer A sees only their own flat(s), schedule, posted receipts and statements.
6. Customer cannot initiate payments, reschedules, refunds or booking edits.
7. Partial/advance payment, refund, reschedule and late-fee cases reconcile to Phase 3 records.
8. Provisional investor profit, approved payable and actual paid profit remain separate and correctly labeled.
9. Flat shared-cost allocation uses the frozen saleable-area basis and reconciles with project shared cost totals.
10. Admin dashboard and exports respect project scope and permissions; unauthorized rows absent.
11. General expenses do not inflate project costs; material transfers do not double-count.
12. Dashboard aggregations reconcile with source ledgers for test datasets.
13. Searchable selects activate above seven options; remote selectors paginate and enforce scope.
14. AJAX filters, drill-downs and pagination work without full page reload; keyboard and mobile UX pass smoke tests.
15. Large report export queues safely, produces permission-scoped private file and expires according to policy.
16. Tests cover 401/403/404/419/422, stale requests, revoked grants and download IDOR attempts.
17. No fake financial numbers in production UI; show empty state if no real data.

## 11. Sequential Claude Code prompts

### MASTER PROMPT — run first

```text
Act as principal Laravel architect and product designer for Key One Properties ERP. Read the Phase 1, Phase 2, Phase 3 and Phase 4 blueprint files in the repository. Inspect actual existing code, database migrations, routes, policies, financial calculation services, files and design components. Phase 4 adds exactly three modules: Investor Portal, Customer Portal, Dashboard & Reports, within the SAME Laravel application/database.

Mandatory: Laravel + Blade + Tailwind + jQuery/AJAX + Select2; premium light UI consistent with Phase 1. More than 7 dropdown choices require search; large lists use remote paginated AJAX search. No unnecessary full reload. Strict server-side authorization and ownership; financial data is permission-filtered per project/flat/field. Investor portal has authorized project/flat progress and financials; customer portal is read-only and restricted to own flats/payments. Profit calculation, approval, payable and payout remain distinct. Full double-entry accounting is NOT implemented until Phase 5.

First produce an implementation compatibility report, gap list, exact migrations, authorization matrix, query/report architecture, test plan and unresolved decisions. Do not modify code or invent missing Phase 3 financial policies until reviewed. Follow existing naming conventions, avoid duplicate tables and show test evidence after each milestone.
```

### PROMPT 1 — Architecture, access links and portal security

```text
Implement Phase 4 portal foundation on existing Phase 1–3 authentication. Add verified user-to-investor/customer links only if absent, granular resource grants, server-side scoped policies, permission checks and audit trail. Implement separate /investor and /customer route groups, portal guards/policies and distinct layouts while sharing one database and existing auth stack. Prevent IDOR, enumeration, cache leaks and unauthorized private downloads. Build policy/feature tests before business UI. Provide migration diff and security matrix.
```

### PROMPT 2 — Premium UI foundation and AJAX components

```text
Extend Phase 1 clean premium light design to investor and customer portals and admin reports. Build reusable Blade KPI cards, responsive sidebar, filter toolbar, report chart/table switcher, status badges, statement list, progress timeline, download control, Select2 remote lookup and AJAX data tables. Use jQuery/AJAX with abortable search, debounce, skeletons, inline errors, toasts, keyboard focus and responsive layouts. Any dropdown with >7 choices is searchable; large sets use remote pagination. No React/Vue/Livewire. Demonstrate desktop/mobile and accessibility smoke tests.
```

### PROMPT 3 — Investor Portal overview and investments

```text
Build Investor Portal overview, My Investments and Investment Detail using Phase 3 authoritative investment, capital, flat and profit ledgers. Show invested capital, investment status, provisional estimated profit, pending approval, approved payable and actual paid profit as separate amounts. Include activity and authorized documents. Investor identity comes only from verified logged-in link. All pages, AJAX endpoints and exports enforce investment association and granular resource grants. Test multi-investor same flat and no cross-investor leakage.
```

### PROMPT 4 — Investor project progress and financials

```text
Build permission-filtered Project Progress, Project Financials and Flat Financials pages. Reuse Phase 2 approved progress updates and Phase 3 frozen saleable-area shared-cost allocation snapshots. Only publish investor-visible progress evidence. Project-wide material, labour, contractor and expense financial details require explicit project/field grants; flat investment alone does not grant aggregate project finances. Explain as-of dates, provisional values and source documents. Test authorization in HTML, AJAX, export and cache layers.
```

### PROMPT 5 — Customer Portal read-only experience

```text
Build Customer Portal overview, My Flats, Installments, Payment History, Statements and Receipt downloads. Use verified customer-user association and Phase 3 authoritative schedules, payment allocations, refunds, advance credits, late fees and reversals. Display total paid, remaining due and next installment accurately. Strictly read-only: no payment, booking, cancellation, refund or reschedule actions. Use polished mobile-first UI, AJAX filters and private authorized downloads. Test multiple flats and cross-customer IDOR prevention.
```

### PROMPT 6 — Executive dashboard and operational reports

```text
Build permission-scoped /admin executive dashboard and report catalog for Projects, Construction, Inventory, Suppliers/Purchasing, Contractors, Expenses, Sales/Bookings, Installments/Collections and Investors. All metrics derive from existing ledgers and approved/posted events. Distinguish commitments, costs, collections, receivables, provisional profit, approved payable and paid amounts. General expenses stay separate from project costs. Implement date/project filters, KPI drill-down, responsive charts with accessible tables, server-side pagination and consistent definitions. Add reconciliation tests and avoid N+1 queries.
```

### PROMPT 7 — Statements, exports and performance

```text
Implement permission-checked private PDF/CSV/Excel report and statement exports using existing project libraries. Queue large exports with database-backed jobs compatible with shared hosting. Add report definitions/runs/files tables only if missing; expiry and download auditing. Revalidate permission at download time and prevent cross-user cache leaks. Add performant aggregate query strategies, safe snapshot invalidation, server-side pagination and realistic load tests. Do not label operational reports audited financial statements.
```

### PROMPT 8 — Integration, QA and release gate

```text
Run full Phase 4 integration QA: migrations on fresh and upgraded DB, portal ownership tests, granular permission matrix, cross-project/cross-investor/customer IDOR attempts, AJAX interactions, private downloads, financial reconciliation, refund/reversal handling, responsive/accessibility checks, report pagination/export, performance and caching. Confirm no duplicate schema or changed Phase 1–3 behavior. Produce exact commands/results, screenshots if browser is available, open risks, deployment checklist and Phase 5 accounting integration contract. Never claim tests passed without running them.
```

## 12. Recommended implementation order and release gate

**Master → 1 Security → 2 UI → 3 Investor → 4 Financials → 5 Customer → 6 Dashboard → 7 Exports → 8 QA.** Commit and test after each accepted prompt. Keep backups before migrations and do not overwrite production financial history.

### Decisions to confirm before production release

- Which investor roles can see **aggregate project financial details**, and which cost categories are hidden by default? Recommended: deny by default; explicit grant per category.
- Whether customers can view project progress. Recommended: no in Phase 4 unless explicitly approved.
- Which Phase 3 profit recognition policy was finalized. If not finalized, show provisional estimates only and never infer a distributable amount.
- Exact export retention, file storage and statement branding rules. Recommended: private storage, configurable retention, branded PDF.

**Definition of done:** All three Phase 4 modules function on the existing application with polished UI, scoped access, accurate ledger-backed figures, fast AJAX interactions, searchable dropdowns, secure exports and passing tests.
