# Phase 3 delivery report

Booking & Sales (M10), Installment & Collection (M11) and Investor Management (M12).
Implementation decisions and the schema diff are in `docs/decisions/phase-3.md`.

## Release notes

| Area | What works |
| --- | --- |
| Sales desk | Overview with unit counts, contract value, collections and a per-project sales and collection summary (CSV / PDF / print); live availability board |
| Bookings | Draft → submit → approve. Approval locks the unit row and takes the unit's single claim slot, so two bookings of one unit cannot both pass; withdraw, reject, cancellation request with approval |
| Sales | From a booking (booking money counted once) or directly; approval transfers or takes the claim, marks the unit sold and posts the contract to the customer statement; agreement, registration and handover stages with files; printable sale document |
| Cancellations and refunds | One cancellation flow for bookings and sales; approval releases the unit and cancels plans; refunds are a separate request → approval → payment (capped at what the customer paid), reversible |
| Price revisions | Unit price changes go through request and approval; history keeps who asked and who approved |
| Installment plans | Monthly, quarterly or custom schedules with live preview; plan must reconcile to the contract (principal + booking credit); approval; reschedule with comparison that keeps paid history |
| Collections | Payment with live allocation preview (oldest due first, fee before principal, rest as advance); posting by a second user; money receipt (print / PDF); reversal with compensating entries and re-application of advance; idempotent submit |
| Dues and late fees | Dues list with aging buckets; per-customer late-fee policy (none, fixed, percent of unpaid principal, grace days, once or monthly); daily job `keyone:collections-daily`; waiver with reason |
| Customer statement | Immutable charge / credit entries with running balance; export and print |
| Investors | Directory, profile with capital, committed, profit due and ledger; agreements per unit with versions (amend → approve closes the old version); live share gauge; share limit of 100% enforced at any date |
| Capital | Contributions and returns, posted by a second user; contributions capped at committed capital, returns at invested capital; reversal; capital receipt / return voucher |
| Cost allocation | Runs per project freeze unit areas and Phase 2 project cost up to a cutoff; unit-tagged expenses are direct, the rest is shared by saleable area with largest-remainder rounding to the paisa; worksheet and source drill-down; stale detection; approval supersedes the previous run |
| Flat profit | Revenue − direct − shared − other cost; investor amounts rounded down, company keeps the explicit remainder; loss gives investors zero; provisional until the client sets the profit policy; approval posts only the change to each investor's payable; comparison with the previous approved calculation; print / PDF |
| Distributions | Payables per investor and unit; request → approval → payout (separate permissions); payout reversal; payout voucher |
| Integration | Approval queue on the dashboard for all Phase 3 documents; dashboard cards (units sold, collected this month, overdue, investor profit due); `keyone:reconcile` now also checks the customer statement and the investor ledger |
| Other | Units list card view (saved per user); content uses the full page width |

## Verification (run in this environment)

| Check | Result |
| --- | --- |
| Automated tests (`php artisan test`, MySQL) | 201 passed, 2186 assertions |
| Clean install | `migrate:fresh --seed` plus sample data on an empty database; `keyone:reconcile` clean for all six checks |
| Upgrade / rollback | Phase 3 migrations rolled back and applied again on the same database |
| Code style (`pint --test`) | passed |
| Asset build (`npm run build`) | passed |
| Dependency audit | `composer audit` and `npm audit` report no advisories |
| Route sweep | Every write endpoint refuses a user without permissions; every record route answers 404 for an unknown public id |
| Browser checks (Playwright, 1440 px, 1920 px, iPhone width) | sales desk, booking → sale, plan and payment forms with live previews, receipt, dues, statement, investor profile and ledger, agreement gauge (including over-allocation), cost run worksheet and sources, profit worksheet, distributions and payout; no console errors |

Tests that cover the blueprint's risk areas: concurrent claim of one unit (`SalesServiceTest`), booking
credit counted once and plan reconciliation, partial and advance payments, fee idempotency, reschedule
keeping paid history, reversal (`CollectionsServiceTest`), share limit across overlapping dates and
competing approvals, versioning, capital caps (`InvestorServiceTest`), rounding, stale runs and
supersede (`CostAllocationTest`), policy gate, payable change on recalculation, loss, overpayment,
stale calculation, missing permission, distributions, reconciliation and portal scoping (`ProfitTest`),
project visibility on every screen (`*HttpTest`).

## Policy decisions still open (blueprint §11)

Profit approval stays blocked until the first two are set in **Settings → Investor Profit Policy**.

1. **Revenue recognition** — when does a unit's profit become distributable: sale approved, registration
   completed, handover completed, or fully collected?
2. **Cost policy** — confirm that unit-tagged expenses are direct and all other project cost is shared by
   saleable area. Also: what happens to approved runs when units are added or areas revised (today: the
   run turns stale and a new run is needed).
3. **Loss treatment** — built: investors get zero, they are not charged. Confirm.
4. **Cancellation / refund** — built: booking money is refundable up to what was paid, no automatic
   deduction; the unit is released when the cancellation is approved. Confirm deductions, if any.
5. **Late fee** — built per customer policy; late fees are not unit revenue. Confirm the default policy.
6. **Payment order** — built: oldest due first, fee before principal, then future items, rest as advance.
7. **Investment share changes** — built: agreements have effective dates and versions; no proration by
   time or capital. Confirm whether new investors may join after cost or revenue milestones.
8. **Cash / bank settlement** — methods are cash, bank transfer, cheque and mobile banking; bank
   reconciliation comes with Phase 5.

## Known limitations

- PDF fonts have no taka glyph, so PDFs print "Tk"; print pages use ৳. PDFs stop at 3,000 rows.
- Fixed-term interest and project-wide investment are not built (not in scope).
- Material, labour and contractor cost cannot be tagged to a unit yet; only project expenses can.
- The customer and investor portals are not built; their read contracts are (below).

## Contracts for later phases

- **Phase 4 (portals):** `App\Contracts\Portal\PortalReadModel::customer(Customer)` and
  `::investor(Investor)` return read-only DTOs (units, schedule, statement; holdings, approved profit,
  ledger). They carry business numbers only, never internal ids, cost worksheets or provisional profit.
  The portal must resolve the customer or investor from the signed-in portal user, never from request input.
- **Phase 4 (reports):** reuse `App\Support\Export\Report` and the list `listing()` queries, which already
  apply project visibility.
- **Phase 5 (accounting):** consume `financial_events` in order. Phase 3 adds `booking.created`,
  `booking.cancelled`, `sale.confirmed`, `sale.cancelled`, `collection.received`, `collection.refunded`,
  `late_fee.assessed`, `late_fee.waived`, `investment.received`, `investment.withdrawn`,
  `profit.payable_approved`, `profit.distribution_approved` and `profit.distribution_paid`; reversals
  carry `reversal_of_id`. The customer statement and investor ledger are operational subledgers;
  `keyone:reconcile` checks them against their source documents.
