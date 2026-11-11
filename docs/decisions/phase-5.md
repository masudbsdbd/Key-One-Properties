# Phase 5 decisions (Accounting & Finance)

Blueprint: `KEY_ONE_PHASE_5_BLUEPRINT.md`. **Status: built** (started 2026-11-11). Delivery report: `docs/phase-5-report.md`; UAT: `docs/uat-checklist-phase-5.md`.

## 1. Client decisions (2026-11-11)

- **Investor capital** is a liability of the company (to be returned), not equity.
- **Construction cost** stays in work in progress until the unit's sale profit is approved, then becomes cost of sale
  (the client left the choice to us).
- **Fiscal year** defaults to July–June and is changeable in Settings.
- **Start of the books:** an opening balance at a chosen start date. Transactions dated before that date are not posted;
  the client may still enter older transactions when wanted (by choosing an earlier start date or entering them dated
  on or after it).
- **Landowner / developer share** stays out of scope (see the future scope list in `phase-4.md`).

### Cash and bank accounts (client's own words, 2026-11-11)

- The company enters its own accounts: cash, bank accounts and mobile banking wallets, each with a name and number
  (examples given: bKash, Nagad, Islami Bank, Agrani Bank).
- An account that has any transaction can never be deleted (it can be made inactive).
- Bank-to-bank transfer in one simple step.
- Deposit and withdrawal.
- Whatever else keeps it easy for non-technical users (see "Ease of use" in `CLAUDE.md`).

## 2. Changes to the blueprint agreed in review (2026-11-11)

- **Account on every payment and receipt.** The six existing payment/receipt forms record only the method
  (cash, bank, cheque, mobile banking). Each gets a "which account" field; existing rows are mapped once.
  This comes with Cash & Bank (Prompt 4), before the integration (Prompt 5).
- **Reuse existing tables:** `financial_events` is the source link and outbox (uuid, idempotency key, status, attempts);
  `report_runs` serves queued exports. No `accounting_source_links` or `accounting_export_jobs` duplicates.
- **Exports** stay CSV with PDF/print, as decided in Phase 4 (no XLSX).
- **Vouchers** are only for money outside the modules (owner's capital, loans, bank charges, …); they cannot record
  customer collections, supplier or contractor payments, which already post from their own screens.
- **Bank reconciliation** starts as manual tick-off against the statement; CSV import and suggestions come later.
- **Posting rules** live in tested code; users choose only which account an event maps to.
- **No cost centres** (project and flat dimensions are enough).
- **Roles:** the permissions ship with two ready roles, Accountant and Accounts Viewer.
- **Ordinary users never enter debit/credit:** existing actions post automatically; manual journals are for the accountant.
- **Posting switch:** a setting with the books' start date; nothing posts to the ledger until it is switched on, so the
  module can be built and tested without waiting for accounting sign-off.

## 3. Policies already decided in Phase 3 (the blueprint lists them as pending)

Revenue is recognised after sale approval when an admin approves the profit calculation; late fees count as unit
revenue; refunds have no deduction by default. See `phase-3.md` and `docs/financial-events.md`.

## 4. Client answers (2026-11-11, second round)

- **Profit approved before construction is finished:** the profit calculation uses the larger of the cost so far
  and the budgeted cost of the unit, so an investor is paid a safe amount early; when the work is finished the
  actual cost is used and the difference is settled.
- **Tax deducted at source:** an optional "Tax deducted" amount on supplier and contractor payments, credited to a
  Tax Payable account. No returns or filings.
- **Deposit / withdrawal** covers both: moving money between the company's own cash, bank and mobile accounts, and
  the owner putting money into the business or taking it out.
- **Cheques** are tracked as In Hand → Deposited → Cleared / Bounced. The status is chosen when the cheque is
  entered, because a cheque may already be cleared or bounced by the time it is recorded.
- **Existing data is dummy data:** existing payments and receipts may be mapped to accounts as we see fit.
- **Access:** the company has an accountant. Every accounting screen sits behind a permission; a seeded
  **Accountant** role carries the permissions the job needs, and the client adjusts it later.

No open questions remain.

## 5. Audit of Phases 1–4 (Prompt 1)

- **Outbox:** every money fact is already written to `financial_events` in the same transaction as the business
  change (`FinancialEventRecorder`), with `event_uuid`, a unique `idempotency_key`, `effective_at`, the source
  morph, project/flat/customer, and `processing_status` (`pending`, `processed`, `failed`, attempts, last error).
  A cancelled document records negated events linked by `reversal_of_id`. Phase 5 posts from this table and adds
  no second source-link table.
- **Subledgers stay the operational truth:** `supplier_ledger_entries`, `contractor_ledger_entries`,
  `customer_statement_entries`, `investor_ledger_entries`, `stock_movements`, `project_cost_allocations`.
  The general ledger is reconciled to them, never the other way round.
- **Gap, money account:** supplier payments, contractor payments, expenses, customer payments, customer refunds,
  investor capital transactions and investor payouts store only a payment method. Each gets a `money_account_id`.
- **Gap, labour:** approved labour is a project cost with no payment record. It credits Wages Payable; wages are
  paid from Cash & Bank as money out against that account.
- **Reuse:** `Approval` (status and self-approval guards), `DocumentNumber`, `report_runs` (queued exports), the
  export framework, `Settings`, activity log, `public_id` on every new table.

## 6. Architecture

- **One writer.** `Accounting\Ledger` is the only code that inserts journal entries and lines. It checks: at least
  two lines, each line one positive side, debits equal credits, postable active accounts, an open period, and a
  unique `source_key` (retries return the first journal). It runs in a transaction and locks the period row.
- **Posted is final.** A posted journal is never edited or deleted (model guard). A reversal is a new journal with
  the sides swapped, linked by `reversal_of_id`.
- **Automatic posting.** After an event is recorded, `EventPoster` resolves it to lines with a rule class per event
  type and posts it (`source_key = event:<uuid>`). The rule reads the source document for the exact parties,
  money account and split. An event with nothing to post is marked processed with a note. A rule that cannot
  resolve (missing mapping) marks the event `failed` with the reason; the business action is not blocked and the
  event shows in the Reconciliation Center, where it is retried.
- **Posting switch.** `accounting.enabled` and `accounting.start_date` (Settings). Events dated before the start
  date are skipped (covered by the opening balance). Nothing posts while the switch is off.
- **Locked periods reject at the source.** When posting is on, recording an event dated in a locked or closed
  period fails the whole business action with a clear message, so no backlog builds up behind a lock.
- **Account mapping.** Rules refer to accounts by a system key (`customer_receivable`, `supplier_payable`, …).
  The seeded chart links each key to an account; an accountant may point a key at another account. Rules are code.
- **Dimensions.** Lines carry optional project, flat and party (customer, supplier, contractor, investor) and the
  money account. Project is required on work-in-progress lines.

## 7. Proposed chart of accounts (seeded, editable)

| Code | Account | Key |
|---|---|---|
| 1010 / 1020 / 1030 | Cash / Bank Accounts / Mobile Banking (one child per money account) | — |
| 1100 | Cheques in Hand | `cheques_in_hand` |
| 1200 | Customer Receivables | `customer_receivable` |
| 1300 | Material Stock | `stock` |
| 1400 | Construction Work in Progress | `wip` |
| 1500 | Advances to Contractors | `contractor_advance` |
| 1700 | Fixed Assets | — |
| 2000 | Supplier Payables | `supplier_payable` |
| 2010 | Goods Received Not Billed | `grni` |
| 2100 | Contractor Payables | `contractor_payable` |
| 2110 | Contractor Retention Payable | `retention_payable` |
| 2150 | Wages Payable | `wages_payable` |
| 2200 | Customer Advances | `customer_advance` |
| 2300 | Investor Capital | `investor_capital` |
| 2310 | Investor Profit Payable | `investor_profit_payable` |
| 2400 | Tax Payable | `tax_payable` |
| 2500 | Expenses Payable | `expense_payable` |
| 2600 | Loans Payable | `loan_payable` |
| 2800 | Estimated Cost to Complete | `cost_to_complete` |
| 3000 / 3100 | Owner's Capital / Owner's Drawings | `owner_capital` / `owner_drawings` |
| 3200 | Retained Earnings | `retained_earnings` |
| 3900 | Opening Balance Equity | `opening_equity` |
| 4000 | Property Sales | `sales` |
| 4100 | Late Fee Income | `late_fee_income` |
| 4200 | Cancellation Deductions | `cancellation_income` |
| 4900 | Other Income | `other_income` |
| 5000 | Cost of Units Sold | `cost_of_sales` |
| 5100 | Investor Profit Share | `investor_profit_expense` |
| 6000 | General Expenses (one child per expense category) | `general_expense` |
| 6100 | Bank Charges | `bank_charges` |
| 6200 | Stock Adjustments | `stock_adjustment` |
| 6300 | Purchase Price Differences | `purchase_variance` |

## 8. Posting matrix

| Event | Debit | Credit |
|---|---|---|
| `purchase.received` | Material Stock | Goods Received Not Billed |
| `purchase.returned` | Supplier Payables (credited part), GRNI (rest) | Material Stock |
| `supplier.bill_approved` | GRNI (value of the goods billed), Purchase Price Differences (rest); a bill with no receipt debits work in progress or general expense | Supplier Payables |
| `purchase.supplier_payment` | Supplier Payables (paid + tax deducted) | Money account, Tax Payable |
| `material.received` | Material Stock | Opening Balance Equity (opening) or Stock Adjustments |
| `material.consumed` | Work in Progress (project) | Material Stock |
| `material.site_returned` | Material Stock | Work in Progress (project) |
| `material.transferred` | Material Stock (to project) | Material Stock (from project) |
| `stock.adjusted` | Material Stock or Stock Adjustments | the other |
| `labour.approved` | Work in Progress (project) | Wages Payable |
| `contractor.bill_approved` | Work in Progress (gross) | Contractor Payables (net), Retention Payable, Advances to Contractors |
| `contractor.retention_released` | Retention Payable | Contractor Payables |
| `contractor.payment` | Contractor Payables, or Advances to Contractors for an advance | Money account, Tax Payable |
| `contractor.advance_refunded` | Money account | Advances to Contractors |
| `expense.approved` | General expense category account, or Work in Progress for a project expense | Expenses Payable |
| `expense.paid` | Expenses Payable | Money account |
| `collection.received` | Money account or Cheques in Hand | Customer Receivables once the unit's revenue is recognised, else Customer Advances |
| `collection.refunded` | Customer Advances / Receivables | Money account, Cancellation Deductions |
| `late_fee.assessed` / `late_fee.waived` | Customer Receivables | Late Fee Income (waiver reverses) |
| `profit.payable_approved` | Customer Receivables; Customer Advances; Cost of Units Sold; Investor Profit Share | Property Sales; Customer Receivables; Work in Progress and Estimated Cost to Complete; Investor Profit Payable |
| `profit.distribution_paid` | Investor Profit Payable | Money account |
| `investment.received` / `investment.withdrawn` | Money account / Investor Capital | Investor Capital / Money account |
| `sale.cancelled` | reverses what was recognised for the unit | |
| `booking.created`, `booking.cancelled`, `sale.confirmed`, `installment.due`, `cost.shared_allocated`, `profit.distribution_approved` | nothing to post (no money or recognition moves) | |

`profit.payable_approved` posts only the change against what the ledger already holds for the unit, because a
unit's profit may be approved more than once. The cost is the larger of the allocated cost and the unit's share
of the budget (client decision); the part not yet spent sits in Estimated Cost to Complete and is released as the
real cost arrives.

## 9. Test strategy

Unit tests for the ledger invariants and reversal; a feature test per rule asserting the journal and that the
trial balance stays balanced; idempotency (same event twice, one journal); locked-period rejection at the source;
subledger-to-control-account reconciliation on the demo data; permission and IDOR tests on every route.

## 10. What changed while building

- **Table names:** `ledger_accounts` (chart of accounts; groups are non-postable parents, so no `account_groups`),
  `journal_lines`, `money_accounts` (cash, bank, mobile), `money_transactions` (transfer, money in, money out),
  `cheques`, `bank_reconciliations`. No posting-rules, source-links, approval-actions or export-jobs tables:
  rules are code in `Accounting\Rules\PostingRules`, links are `financial_events` and `journal_entries.source_*`,
  approvals are on the journal with the activity log, exports use the existing framework.
- **Supplier bills:** the debit to Goods Received Not Billed is the received value of the billed goods; anything
  else on the bill (price difference, carriage, tax, discount) goes to Purchase Price Differences & Charges, so
  stock and project cost in the ledger stay equal to the stock and cost records.
- **Purchase returns** credit stock at the stock movement's value (average cost), not the supplier's price.
- **Payment events** of suppliers and contractors now carry the cash paid; the settled amount and the tax
  deducted are in the payload.
- **Customer name:** `Customer::name` is an accessor for `full_name`, shared with the other parties.
- **Old payments with no account** use the default account per kind from Accounting Settings, or the only
  active account of that kind.
- **Year closing** entries have journal type `closing` and are left out of Profit & Loss, so a closed year still
  reports what it earned.
- **Bank statement import** and suggestions are not built; reconciliation is by ticking (agreed in review).
- **Cheques** are tracked for customer payments only.
