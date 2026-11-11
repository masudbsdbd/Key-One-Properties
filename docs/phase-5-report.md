# Phase 5 delivery report

Accounting & Finance (module 16). Blueprint: `KEY_ONE_PHASE_5_BLUEPRINT.md`. Decisions and the posting matrix:
`docs/decisions/phase-5.md`.

## Release notes

- **Chart of accounts** (`/accounting/accounts`): 37 seeded accounts grouped as assets, liabilities, equity,
  income and expenses, each with its balance. Accounts can be added, renamed and made inactive; accounts the
  system posts to cannot be deleted.
- **Journals** (`/accounting/journals`): manual journals move Draft → Waiting for Approval → Approved → Posted.
  A posted journal is never edited or deleted; it is corrected by a linked reversal. The form shows both totals
  and the difference while typing.
- **Cash & Bank** (`/accounting/cash-bank`): the company adds its own cash, bank and mobile banking accounts with
  an opening balance. Deposit, Withdraw and Transfer sit on each account; a statement shows every line with a
  running balance. An account with transactions cannot be deleted, only made inactive.
- **Account on every payment:** supplier payments, contractor payments, expenses, customer payments, refunds,
  investor capital and investor payouts name the account the money went through. Supplier and contractor
  payments take an optional tax deducted at source.
- **Cheques** (`/accounting/cheques`): a customer's cheque is In Hand, Deposited, Cleared or Bounced; the status
  is chosen when the cheque is entered. Money reaches the bank account only when the cheque clears; a bounced
  cheque takes the payment back.
- **Automatic posting:** every transaction of Phases 2–4 posts its own balanced journal once, straight from the
  action that caused it. A transaction that cannot post is kept, shown with its reason, and retried.
- **Financial statements:** Profit & Loss (with the investors' share shown separately and a comparison period),
  Balance Sheet, Cash Flow, Project and Unit Financials, Trial Balance and General Ledger. Every figure opens the
  entries behind it. All export to CSV, PDF and print.
- **Receivables and Payables:** what each customer owes less advances held, and what is owed to each supplier,
  contractor and investor, with the same "to pay" figure as the overview.
- **Accounting overview** (`/accounting`): cash, receivables, payables and profit, with every report one click away.
  The main dashboard shows each cash and bank balance to users allowed to see them.
- **Exports:** account statements and the journal list export to CSV, PDF and print, like the statements.
- **Payment forms** fill in the account when only one fits the method.
- **Months & Years** (`/accounting/periods`): lock and unlock a month, close and reopen a fiscal year. Closing
  moves the year's profit to Retained Earnings.
- **Reconciliation** (`/accounting/reconciliation`): seven operational totals set against the ledger, and the
  list of transactions waiting to be posted. Bank accounts are reconciled against their statements by ticking
  entries until the difference is zero.
- **Profit rule:** a unit's profit now counts the unit's share of the project budget that is not spent yet, so a
  unit sold before the building is finished does not show profit that later cost will use up. When the project
  is complete the real cost is used and the difference is settled.
- **Settings** (`/accounting/settings`): switch automatic posting on from a start date, choose the fiscal year's
  first month, pick the accounts used for payments entered before accounts existed, re-point posting keys, and
  enter opening balances.
- **Roles:** Accountant (all accounting permissions) and Accounts Viewer are seeded.

## Going live

1. `php artisan migrate`
2. `php artisan db:seed --class=RolesAndPermissionsSeeder --force` (adds the accounting permissions and roles)
3. Give the accountant the Accountant role.
4. Cash & Bank → add every cash, bank and mobile account with its balance on the start date.
5. Accounting Settings → Opening Balances for anything else the company owns or owes that is not in the system
   (assets, loans, owner's capital).
6. Accounting Settings → Automatic Posting **On**, with the first day of the books. Transactions dated on or
   after that day are posted at once; earlier ones are left to the opening balances.
7. Reconciliation → every line should read "Matches" and nothing should be waiting.

Rollback: switch Automatic Posting off. Nothing in Phases 1–4 depends on the ledger; the tables added by
`2026_11_11_100000`, `2026_11_11_110000` and `2026_11_11_120000` can be rolled back with `php artisan migrate:rollback --step=3`.

The scheduler runs `keyone:post-ledger` every 15 minutes to retry anything that is waiting.

## Verification (run in this environment)

- `php artisan test`: 345 tests, all passing; 51 of them are in `tests/Feature/Phase5`.
- `LedgerIntegrationTest` runs the whole demo scenario with the books on: every event posts once, each journal
  balances, and the statements agree with each other (profit and loss with the balance sheet, cash flow with the
  cash and bank accounts, party lists with their control accounts, all seven reconciliation lines).
- `RevenueRecognitionTest` follows one unit through an advance, recognition, a recalculation after completion and a
  cancellation, checking each account after every step.
- `PostingRuleCoverageTest` fails if an event type has no posting rule or a rule names an account key that is
  not in the chart; it also opens every statement as PDF, print and CSV.
- `migrate:fresh --seed` on an empty database: every demo event posted, none failed, all seven reconciliation
  lines match, balance sheet difference 0.00.
- Browser scan of the accounting pages at 1440 px: no script errors, no clipped tables, no unnamed controls.
- Tests run at a fixed midday so the result does not depend on the hour (two older tests failed between
  midnight and 6 am Dhaka time; the application code was right, the tests compared against the server date).

## Acceptance scenarios (blueprint section 9)

| # | Scenario | Covered by |
|---|---|---|
| 1 | Balanced journal posts, unbalanced is rejected | `LedgerTest`, `JournalHttpTest` |
| 2 | Duplicate source event gives one journal | `LedgerTest::test_the_same_source_posts_once`, `LedgerIntegrationTest` |
| 3 | Posted journal cannot be edited or deleted; reversal is a linked inverse | `AccountingSchemaTest`, `LedgerTest` |
| 4 | Locked period rejects postings, automatic ones included | `LedgerTest`, `PeriodAndReconciliationTest` |
| 5 | Supplier invoice and payment do not double count cost | `LedgerIntegrationTest` (project cost equals work in progress plus cost of sales) |
| 6 | Receipt, issue and transfer reconcile stock value | `LedgerIntegrationTest` (stock account equals stock records) |
| 7 | Collection reduces the receivable once; an advance is not revenue | `RevenueRecognitionTest`, reconciliation line "Money received from customers" |
| 8 | Cancellation and refund leave a traceable settlement | `RevenueRecognitionTest` (cancellation), demo scenario (refund) |
| 9 | Profit approval and payout are separate events | `profit.payable_approved`, `profit.distribution_paid`; reconciliation line "Profit owed to investors" |
| 10 | General expenses stay company-level | `expense.approved` rule; `FinancialReportsTest` (project filter) |
| 11 | Shared cost allocations reconcile to project cost | Phase 3 `CostAllocationTest`; ledger keeps cost at project level |
| 12 | Trial balance balances; statements come from posted lines | `FinancialReportsTest` |
| 13 | Transfer nets to zero across accounts and posts once | `CashBankTest` |
| 14 | Out-of-scope projects and private data stay hidden | `FinancialReportsTest`, `AuthorizationRegressionTest` |
| 15 | Actions update the page without a full reload | report filters, cheque and reconciliation actions, bank ticking |
| 16 | Backlog is visible and retried, never dropped | `PeriodAndReconciliationTest`, `LedgerIntegrationTest` |

## Accountant sign-off

The system posts with the treatments below. Each is a setting or a posting key, not hard-wired policy; the
accountant should confirm them before the books are switched on for real figures.

| Treatment | Now | Where to change |
|---|---|---|
| Sale revenue recognised | When the unit's profit calculation is approved | Settings › Investor Profit Policy |
| Construction cost | Work in progress until the unit is recognised, then cost of units sold | Posting rule |
| Cost of a unit sold early | Larger of cost so far and the unit's budget share; settled at completion | Project budget, project status |
| Investor capital | Liability (Investor Capital) | Accounting Settings › account mapping |
| Investors' share of profit | Expense, credited to Investor Profit Payable when approved | Account mapping |
| Customer money before recognition | Customer Advances (liability) | Account mapping |
| Late fees | Income when charged | Account mapping |
| Refund deduction | Income (Cancellation Deductions) | Account mapping |
| Purchase price differences, carriage, tax on bills | One expense account; stock stays at received value | Account mapping |
| Tax deducted at source | Tax Payable when the payment is made | Payment form, account mapping |
| Labour | Work in progress against Wages Payable; wages paid from Cash & Bank | Withdraw › Labour wages |
| Fiscal year | July–June | Accounting Settings |

## Known limits

- Cheque tracking covers cheques received from customers. A cheque given to a supplier, contractor or investor
  is treated as a bank payment on its date.
- Bank statements are reconciled by ticking entries; statement file import is not built.
- No depreciation, payroll, VAT returns, multiple currencies or cost centres (out of scope in the blueprint).
- Landowner share is not modelled (see the future scope list in `docs/decisions/phase-4.md`).
- Opening balances are entered once as a single journal; entering them again replaces the earlier figures.
