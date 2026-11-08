# Phase 2 delivery report

## Delivered

| Area | What works |
| --- | --- |
| Shared foundation | `PREFIX-YYYY-NNNNN` document numbers from row-locked sequences, polymorphic private attachments, `project_cost_allocations` as the single source of project cost, reversible `financial_events`, self-approval guard, exact decimal math (no floats) |
| Construction | Work packages, milestones, progress updates with evidence and approval, investor-safe visibility flags, labour entries with approval and cost posting, project cost breakdown with CSV |
| Material & Warehouse | Catalogue, project and company warehouses, weighted-average stock ledger (immutable movements, idempotent posting), receive, issue (posts material cost), adjustments with approval, borrow → issue → return → convert, permanent transfers with preview and reversal, stock reconciliation |
| Supplier & Purchase | Supplier directory with statement, running balance, CSV and payable aging; purchase orders with discount and tax lines, submit / approve / reject / cancel / close; partial goods receipts at landed cost; returns (credit only for billed goods); bills from received lines plus charges; payments with bill allocation; reversals |
| Contractors | Directory and ledger; work orders with retention %; measurements capped at the contract value; running bills with advance adjustment, retention and other deductions; advances and bill payments; settlement through a retention-release bill; reversals |
| Expenses | Category tree with allowed scope; general and project expenses; draft → submitted → approved → paid, reject and resubmit, reversal; append-only history; receipts; report filters (status, scope, project, category, date range) with totals and CSV |
| Integration | Project tabs for cost, materials, purchases, contractors and expenses; dashboard payables, monthly expenses, low stock and an "Awaiting You" approval queue; `keyone:reconcile` (stock, supplier ledger, contractor ledger, project cost) scheduled nightly |
| UI | Tooltips on icon-only controls and disabled controls (with the reason), lazy tables in hidden tabs, filters preset from the URL, CSV links that carry the current filters |

## Verification (run in this environment)

| Check | Result |
| --- | --- |
| Automated tests (`php artisan test`, MySQL) | 158 passed, 1295 assertions |
| Clean install | `migrate --seed` on an empty database, `keyone:reconcile` clean, then `migrate:reset` succeed |
| Code style (`pint --test`) | passed |
| Asset build (`npm run build`) | passed |
| Dependency audit | `composer audit` and `npm audit` report no advisories |
| Browser checks (Playwright, 1440px and iPhone 13) | supplier → PO → GRN → bill → payment; work order → measurement → bill → payment; expense create → submit → approve → pay; project tabs; dashboard; no console errors; no horizontal overflow on phone |

Blueprint acceptance scenarios 1–14 and 18 are covered by tests (`StockLedgerTest`,
`PurchasingServiceTest`, `ContractorServiceTest`, `ExpenseServiceTest`, the HTTP tests and
`ReconciliationTest`). The route sweep now also checks that every write endpoint refuses a
user without permissions and that every record route answers 404 for an unknown id.

## Known limitations

- Supplier payments are company-level: anyone with `purchases.view` can see them. Bills and
  orders follow project visibility.
- Measurements cannot exceed the contract value; a variation needs a new work order.
- Tax, VAT and withholding are plain amounts. Rules belong to Phase 5.
- Exports are CSV only.
- No printable notes (PO, GRN, transfer) yet.
- The investor portal is not built; only `visibility` / `published_at` and investor-safe scopes exist.

## Contract for later phases

- **Phase 3 (sales):** read project cost from `project_cost_allocations` (`CostAttribution::totals`). Do not add new cost writers outside that service.
- **Phase 4 (portals):** use `ConstructionProgressUpdate::investorVisible()` and `toInvestorArray()`; never expose cost or ledger fields.
- **Phase 5 (accounting):** consume `financial_events` in order. Every event has a unique idempotency key, a module, a source document and, for reversals, `reversal_of_id`. Supplier and contractor ledgers are operational subledgers to be reconciled with the general ledger; `keyone:reconcile` is the starting point.
