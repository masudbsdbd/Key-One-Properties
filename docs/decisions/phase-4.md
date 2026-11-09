# Phase 4 decisions and plan

Spec: `KEY_ONE_PHASE_4_BLUEPRINT.md`. This file maps the blueprint onto the code that exists after Phase 3
and records the defaults chosen where the blueprint leaves a decision open.

## 1. Compatibility matrix

| Phase 4 needs | Already exists | Plan |
| --- | --- | --- |
| One app, one database | Laravel 13 app; internal routes at the site root behind `auth`, `active`, `password.changed`, `two-factor.configured` | Add `/investor` and `/customer` route groups with their own layouts and middleware. Internal pages stay where they are (see §2) |
| Users and login | `users` (email or mobile login, lockout, TOTP, session rotation, "log out other sessions"), Spatie roles/permissions | Reuse the same login page and guard. Add `users.account_type` (`staff`, `investor`, `customer`); the login redirects by type and staff-only middleware keeps portal accounts out of internal pages |
| User ↔ investor link | `investors.portal_user_id` (nullable, never used) | New `investor_user_links` (verified, active, revocable). `portal_user_id` stays unused and is documented as superseded |
| User ↔ customer link | none | New `customer_user_links` |
| Granular grants | Spatie permissions (role-wide only) | New `portal_access_grants` (per project / flat / agreement capability, expiry, revoke audit) |
| Access audit | Spatie activity log (`activity_log`) | New narrow `portal_access_audits` for portal sign-in, downloads, exports and denied attempts (high volume, no model events); admin changes to links and grants go to the activity log |
| Investor data | `investors`, `investor_flat_agreements` (versions), `investor_capital_transactions`, `investor_ledger_entries`, `flat_profit_calculations`, `investor_profit_allocations`, `investor_profit_distributions`, `investor_payouts` | Reuse. `App\Contracts\Portal\PortalReadModel` (Phase 3) is the starting point and is extended, not duplicated |
| Customer data | `flat_sales`, `flat_bookings`, `installment_plans` / items, `customer_payments`, `payment_allocations`, `money_receipts`, `late_fee_assessments`, `installment_reschedules`, `customer_refunds`, `customer_statement_entries` | Reuse. Balances come from the statement and allocations, never from a portal total |
| Progress for investors | `construction_progress_updates` with `status`, `visibility = investor`, `published_at`, `scopeInvestorVisible()`, `toInvestorArray()`; stages and milestones | Reuse as the investor publication. No `progress_publications` table. Customer progress stays off |
| Flat cost and profit | `flat_cost_allocation_runs` (frozen areas, source max id), `flat_cost_allocations`, profit snapshots | Reuse the latest approved run; never recompute from current areas |
| Project cost | `project_cost_allocations` (single writer, reversals, optional `flat_id`) | Reuse for project financials; general expenses never appear (they have no project) |
| Files | Polymorphic `attachments` on the private disk, served through an authorised controller | Add `attachments.portal_visible` (default off). Portal downloads go through portal controllers that check ownership first |
| Generated documents | `Document` (receipts, vouchers, agreement, capital receipt, payout voucher), `Report` (CSV / PDF / print) | Reuse for portal statements and receipts |
| Exports | Synchronous `Report` (CSV, PDF ≤ 3,000 rows, print) | Add `report_runs` and `report_files` with a database-queue job for large exports. Report definitions live in code (a registry class), not in a table |
| Excel | No spreadsheet library | CSV opens in Excel; a true `.xlsx` needs a new dependency → not added without approval |
| Dashboards | `DashboardStats` cards, `ApprovalQueue`, sales overview summary | Extend into an executive dashboard; caching through Laravel cache with keys that include the user id (no `dashboard_snapshots` table) |
| Queue | `QUEUE_CONNECTION=database` | Reuse; deployment needs `queue:work --stop-when-empty` from cron on shared hosting |

## 2. Deviations from the blueprint

- **Admin path.** The blueprint names `/admin`; the project security rule says the admin panel must not
  live on `/admin`. Internal pages keep their current paths; reports go under `/reports` and the
  executive dashboard stays at the site root. `/investor` and `/customer` are added as specified.
- **Report definitions** are a code registry (key, name, group, permission, filters, basis) instead of a
  `report_definitions` table, so definitions ship with the code that runs them. `report_runs` and
  `report_files` are added.
- **Dashboard snapshots** use the cache store, with per-user keys and a short time to live, instead of a
  `dashboard_snapshots` table.
- **Progress publication** reuses the Phase 2 visibility flag and publish date on progress updates.

## 3. Migrations (Phase 4)

1. `users.account_type` string(20) default `staff`, indexed.
2. `investor_user_links` (id, user_id FK, investor_id FK, is_active, verified_at, verified_by FK nullable,
   revoked_at, revoked_by FK nullable, created_by FK, timestamps; UNIQUE(user_id, investor_id)).
3. `customer_user_links` (same columns with customer_id).
4. `portal_access_grants` (id, public_id, user_id FK, portal_type, resource_type, resource_id, capability,
   granted_by FK, starts_at, expires_at nullable, revoked_at nullable, revoked_by FK nullable,
   timestamps; indexes as in the blueprint).
5. `portal_access_audits` (id, user_id FK nullable, portal_type, action, resource_type nullable,
   resource_id nullable, result, ip_hash nullable, created_at; index(user_id, created_at)).
6. `attachments.portal_visible` boolean default false.
7. `report_runs`, `report_files` (Prompt 7).

All additive; no Phase 1–3 column is changed.

## 4. Authorisation matrix

A portal request passes only if **every** row applies:

| Layer | Investor portal | Customer portal |
| --- | --- | --- |
| Account | signed in, active, `account_type = investor` | signed in, active, `account_type = customer` |
| Link | an active, verified `investor_user_links` row (revocation applies on the next request) | an active, verified `customer_user_links` row |
| Permission | `investor.portal.view` plus the page capability | `customer.portal.view` plus the page capability |
| Association | the record belongs to the linked investor (agreement, capital, ledger, payout) or to a unit the investor holds through an active or closed agreement | the record belongs to the linked customer (sale, booking, plan, payment, receipt, refund) |
| Grant | project financials and per-category cost need an unexpired, unrevoked grant for that project (or unit) — role permissions alone do not open them | none in Phase 4 |
| Response | inaccessible records answer 404, never 403, so existence is not disclosed | same |

Default capabilities of the seeded **Investor** role: `investor.portal.view`, `.investments.view`,
`.project.progress.view`, `.flat.financials.view`, `.profit.view`, `.documents.download`,
`.statements.export`. **Not** included (grant per project): `.project.financials.view`, `.cost.material.view`,
`.cost.labour.view`, `.cost.contractor.view`, `.cost.expense.view`. Without a cost grant the flat page
shows only direct and shared totals, not the category breakdown.

Default capabilities of the seeded **Customer** role: `customer.portal.view`, `.flats.view`,
`.installments.view`, `.payments.view`, `.statements.download`.

Internal management: `portal.links.manage` (create, verify, revoke links and portal accounts),
`portal.grants.manage`, `reports.view` plus one permission per report group, `reports.export`,
`dashboard.executive.view`.

## 5. Query and report architecture

- Portal pages call scoped read services (`InvestorPortal`, `CustomerPortal`) built on the Phase 3 read
  model. Each service is constructed for one resolved investor or customer, so a query cannot run without
  that scope; serializers return only allowed fields (no internal ids, no other investors, no supplier or
  contractor details).
- Reports are classes in a registry; each declares its permission, filters, basis (posted / approved /
  estimated), columns and a scoped query. The same class feeds the screen (paginated), the synchronous
  export and the queued export.
- Dashboard widgets are cached per user and filter set for a few minutes; revoking a link or grant
  clears that user's cache entries.

## 6. Test plan

Feature tests per prompt, plus a route sweep extension: every `/investor` and `/customer` route answers
for its owner, 404 for another owner's records, 302 to sign-in for guests, and nothing for staff or the
other portal type. Cases from blueprint §10 map to: `PortalAccessTest` (1–4, 16), `CustomerPortalTest`
(5–7), `InvestorPortalTest` (3, 8–9), `ExecutiveDashboardTest` and `ReportsTest` (10–12, 15),
browser checks for 13–14, and an empty-state check for 17.

## 7. Decisions (defaults until the client says otherwise)

| Question (blueprint §12) | Default built |
| --- | --- |
| Aggregate project financials for investors | Denied by default; explicit grant per project and per cost category |
| Customers viewing project progress | Off in Phase 4 |
| Phase 3 profit recognition | Finalised 2026-11-09 (after sale approval, admin approves). Portal shows approved payable and paid from the ledger; draft or submitted calculations appear only as "Estimated" |
| Export retention and storage | Private disk, 7-day expiry (configurable), PDFs carry the app branding |
| Excel | CSV (opens in Excel) until an `.xlsx` library is approved |

## 8. Implementation checklist

- [x] P1 Portal foundation: account type, links, grants, audits, middleware, portal route groups, tests
- [x] P2 Portal UI foundation: layouts, navigation, shared components
- [x] P3 Investor overview, investments, investment detail
- [ ] P4 Investor project progress, project financials, flat financials
- [ ] P5 Customer portal
- [ ] P6 Executive dashboard and report catalog
- [ ] P7 Queued exports, report runs and files
- [ ] P8 Integration QA and release
