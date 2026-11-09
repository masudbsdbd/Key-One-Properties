# Phase 4 delivery report

Investor Portal, Customer Portal, Dashboard & Reports. Built on the Phase 1–3 application without
changing its schema or behaviour; decisions are in `docs/decisions/phase-4.md`.

## Release notes

- **Portal foundation:** `users.account_type`; investor and customer portal links with maker-checker
  verification; per-resource grants with expiry; portal access audit; `staff` and `portal:*` middleware.
  Portals live at `/investor` and `/customer`.
- **Investor portal:** overview, investments with timeline and documents, project progress, granted
  project and unit financials, statement with export. Profit is shown as Estimated, Pending approval,
  Approved (unpaid) and Paid, never mixed.
- **Customer portal:** overview per unit, installment schedule with late fees, payments with receipts,
  statement with export; read-only.
- **Executive dashboard and reports:** `/executive` with nine permission-scoped widgets; a catalog of
  twelve reports with filters kept in the address bar, CSV/PDF/print export, and queued exports
  (`report_runs`, `report_files`, private storage, 7-day expiry, download re-checks permission).
- **Record pages:** unit page shows the whole deal (buyer, milestones, schedule, payments, ledger,
  refunds, investors, earlier deals); customer page has Sales and Payments tabs; project page shows
  actual cost and remaining budget.
- **Linked references:** project, unit, customer, supplier, contractor, investor and document numbers in
  lists and detail pages open their own page when the user may see it (`App\Support\RecordLink`,
  `<x-ref>`); otherwise they stay plain text.
- **Fixes:** plain route throttles now count per route (exports were blocked by dropdown searches);
  cards and badges no longer overflow on phones.

## Verification (run in this environment)

| Check | Command | Result |
| --- | --- | --- |
| Test suite | `php artisan test` | 227 passed, 2,675 assertions |
| Portal isolation gate | `tests/Feature/Phase4/PortalIsolationTest.php` | Every portal route answers 404 for other owners' records; every internal route (250+) answers 404 to portal accounts; no route outside the staff or portal groups |
| Fresh install | `RefreshDatabase` migrates the test DB from empty on every run | Passes |
| Upgrade from Phase 3 | Phase 3 code (40cbf8c) migrated and seeded, then current `migrate` + `RolesAndPermissionsSeeder` | Only the 6 new tables added; payments, statement, investor ledger, project cost and stock totals identical before and after; no Phase 1–3 role lost a permission (Admin +15, new Investor and Customer roles) |
| Rollback | `migrate:rollback --step=2` then `migrate` | Clean both ways; Phase 3 tables untouched |
| Reconciliation | `php artisan keyone:reconcile` | All six checks match (stock, supplier, contractor, project cost, customer statement, investor ledger) |
| Dependencies | `composer audit`, `npm audit --omit=dev` | No advisories, 0 vulnerabilities |
| Accessibility and layout | Playwright sweep of 19 staff and portal pages at 390 px and 1440 px | No horizontal overflow, no unnamed buttons or links, no unlabelled inputs, no images without alt |
| Performance | Query count per request on dev data | Portal pages 6–25 queries, report tables 4–10, executive shell 9; all under 55 ms locally. Widgets load separately and are cached per user for 5 minutes |

## Client decisions (2026-11-09)

| Question | Decision |
| --- | --- |
| Investor access to aggregate project financials | The company decides: off by default, opened per investor and project/cost category, or for all investors through the role |
| Customers viewing project progress | Off by default; opened per customer on a project or unit, or for all customers through the role |
| Export retention | Private storage, 7 days (`REPORT_EXPORT_DAYS`); awaiting client confirmation |
| Excel exports | Keep CSV (opens in Excel) |

## Open risks

- Queued exports and daily jobs need the scheduler cron; without it exports stay "Queued".
- Portal sign-in uses the same login page as staff; the login path should be non-obvious in production.
- PDFs print "Tk" (font has no taka glyph) and stop at 3,000 rows; larger exports should use CSV.
- Report figures are operational, not audited accounts, until Phase 5.
- Reference links check permissions and project visibility; record-level rules that depend on other data
  (e.g. contractor bills) run one visibility check per row, which is fine at current volumes.

## Phase 5 accounting contract

- Consume `financial_events` in order (Phase 2–3 event types are listed in `docs/phase-3-report.md`);
  reversals carry `reversal_of_id`. Phase 4 adds no financial events: portals and reports only read.
- The customer statement, investor ledger, supplier and contractor ledgers remain operational subledgers;
  `keyone:reconcile` must stay green after the general ledger is introduced.
- Reports declare their basis (`BaseReport::basis()`); accounting reports should be added to
  `ReportRegistry` in a new `accounting` group with their own permission, reusing the queued export path.
- Portal DTOs (`App\Contracts\Portal`) carry business numbers only; accounting must not expose journal
  internals to portals.
