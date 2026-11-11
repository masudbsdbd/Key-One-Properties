# KEY ONE PROPERTIES ERP — PHASE 5
## Accounting & Finance Management | Database Architecture and Claude Code Prompts

**Module 16 of 16 | Phase 5 of 5 | Laravel + MySQL + Blade + Tailwind + jQuery/AJAX + Select2**

> This blueprint extends the existing Phase 1–4 Laravel ERP. Do not rebuild it. The requirements below are a proposed technical implementation plan. Accounting policies that require management/accountant approval are identified explicitly.

## 1. Confirmed context and non-negotiable principles

- One centralized application/database, separate permission-based internal and portal experiences.
- Full double-entry accounting starts as a functional module in Phase 5; prior phases must already have accounting-ready source events.
- Integrate project construction, materials, suppliers, contractors, general/project expenses, flat sales, customer installments, investor capital and profit distribution.
- Shared construction costs are allocated to flats by saleable area; do not allocate the same expense twice.
- Multiple investors per flat, aggregate profit-share maximum 100%; profit approval requires an authorized user with dynamic permission.
- General company expenses must not automatically become project costs.
- Posted accounting documents are immutable; corrections use controlled reversal and replacement.
- Use DECIMAL (never floating point), MySQL transactions, row locks, idempotency keys, audit trails, authorization and project-scoped access.
- Premium clean light UI consistent with Phase 1–4. For dropdowns with more than 7 choices use searchable Select2-style controls; large datasets use AJAX server-side search and pagination. Appropriate CRUD, posting, filtering and report drilldowns should work without full page reload.

## 2. Scope

1. Chart of Accounts (COA), account types, account groups, project/flat dimensions.
2. Fiscal years, accounting periods, lock/close and opening balances.
3. Double-entry journal engine, general ledger and posting rules.
4. Manual Journal Vouchers, Receipt Vouchers, Payment Vouchers, Contra/Transfer Vouchers.
5. Cash and bank accounts, transfers, bank reconciliation.
6. Accounts Receivable and customer subledger reconciliation.
7. Accounts Payable and supplier/contractor subledger reconciliation.
8. Accounting integration with Phases 1–4 including stock valuation, expense and investment events.
9. Trial Balance, General Ledger, Profit & Loss, Balance Sheet, Cash Flow, Project/Flat Financial Reports.
10. Approvals, reversals, period controls, exports, audit, tests and migration/reconciliation tools.

**Not automatically included:** tax-compliance filings, payroll, fixed asset depreciation, multicurrency, statutory VAT automation, consolidation of multiple legal entities, automated bank feeds. These require separate decisions and possibly future scope.

## 3. Accounting design principles

- One **posting service** owns all journal writes; controllers and other modules never directly insert journal lines.
- Every posted journal has at least two lines, total debit = total credit in the same currency, nonnegative debit/credit, and at most one nonzero side per line.
- Journal source uniqueness: source_type + source_id + event_type + posting_version (or unique immutable source_event_uuid). Idempotent retries return the original posting.
- Transactions may be approved before posting. Approval and posting are separate states/permissions.
- The ledger is append-only after posting. Reversal posts an equal-and-opposite journal linked to the original, never edits/deletes original lines.
- Dates and periods: transaction date, document date, posted_at, fiscal_period_id; disallow posting to locked periods. Backdating is permission-controlled.
- Store base currency BDT; leave extensible currency fields but don't implement FX conversion unless approved.
- Project and flat dimensions are optional on account lines but mandatory when the source event represents project/flat-attributed cost or revenue.
- A single operational event should produce a single canonical accounting event and a deterministic posting rule. Do not separately post the same cost from goods receipt, supplier bill, project expense and inventory issue.
- Subledgers remain source of operational truth for invoices, payments and dues; GL is accounting truth. Reconcile rather than silently overwrite.
- Financial statements must be derived from **posted** entries only, with explicit date ranges and period lock state.

## 4. Proposed database schema

Adapt names to the actual existing schema before migrations. All tables include appropriate created_at/updated_at, created_by/approved_by/posted_by, indexes, foreign keys and audit metadata where applicable.

### Core accounting

| Table | Key columns and constraints |
|---|---|
| `account_groups` | id, parent_id nullable, code unique, name, category (asset/liability/equity/income/expense), sort_order |
| `chart_of_accounts` | id, group_id, parent_id nullable, code unique, name, account_type, normal_balance (debit/credit), is_control_account, is_postable, is_active, requires_project, requires_flat, currency_code default BDT |
| `fiscal_years` | id, name, start_date, end_date, status (open/closing/closed); prevent overlapping years |
| `accounting_periods` | id, fiscal_year_id, start_date, end_date, status (open/locked/closed), locked_by, locked_at; prevent overlapping periods |
| `journal_entries` | id, journal_no unique, journal_type, source_event_uuid nullable unique as appropriate, source_type nullable, source_id nullable, event_type nullable, document_date, accounting_date, fiscal_period_id, narration, status (draft/submitted/approved/posted/reversed), total_debit DECIMAL(18,2), total_credit DECIMAL(18,2), currency_code, approved_by nullable, posted_by nullable, posted_at nullable, reversal_of_id nullable, idempotency_key unique, version |
| `journal_entry_lines` | id, journal_entry_id, line_no, account_id, description, debit DECIMAL(18,2) default 0, credit DECIMAL(18,2) default 0, project_id nullable, flat_id nullable, customer_id nullable, supplier_id nullable, contractor_id nullable, investor_id nullable, cost_center_id nullable, reference_type nullable, reference_id nullable; unique(journal_entry_id,line_no) |
| `accounting_posting_rules` | id, event_type unique, debit_account_id, credit_account_id, rule_version, active, config JSON; advanced rules should use tested PHP rule resolvers rather than arbitrary dynamic expressions |
| `accounting_source_links` | id, source_event_uuid unique, journal_entry_id unique nullable, source_module, source_type, source_id, event_type, status, error_code nullable, last_attempt_at nullable, attempts; index source keys |
| `accounting_reconciliation_runs` | id, scope, started_at, finished_at nullable, status, discrepancies_count, initiated_by, summary JSON |
| `accounting_reconciliation_items` | id, run_id, source_type, source_id, expected_amount, actual_amount, difference_amount, status, details JSON |
| `accounting_approval_actions` | id, document_type, document_id, actor_id, action, reason nullable, metadata JSON, created_at; append-only |

### Cash, bank and vouchers

| Table | Key columns and constraints |
|---|---|
| `cash_bank_accounts` | id, name, kind (cash/bank), bank_name nullable, account_number_masked nullable, branch nullable, coa_account_id unique, currency_code, is_active |
| `accounting_vouchers` | id, voucher_no unique, type (journal/receipt/payment/contra), voucher_date, payee_payer nullable, description, status, amount DECIMAL(18,2), cash_bank_account_id nullable, journal_entry_id nullable unique, created_by, approved_by nullable, posted_at nullable |
| `bank_statement_imports` | id, cash_bank_account_id, period_start, period_end, file_id, imported_by, imported_at, status |
| `bank_statement_lines` | id, import_id, external_reference nullable, transaction_date, description, debit_amount, credit_amount, balance nullable, match_status; import+stable fingerprint unique |
| `bank_reconciliation_matches` | id, statement_line_id, journal_entry_line_id, matched_amount, matched_by, matched_at; prevent duplicate over-matching |

### Optional operational helpers

| Table | Key columns and constraints |
|---|---|
| `cost_centers` | id, code unique, name, project_id nullable, is_active |
| `accounting_opening_balances` | id, fiscal_year_id, account_id, dimension_hash, debit, credit, journal_entry_id nullable, status; openings must be posted as a balanced journal |
| `accounting_export_jobs` | id, report_type, filters JSON, requested_by, status, private_file_id nullable, expires_at |

**Important:** Do not create duplicate customer/supplier/contractor ledgers if Phases 2–3 already own them. Reference and reconcile existing tables. Use actual model/table names from the repository.

### Constraints and indexes

- Unique COA code; no postings to parent/nonpostable/inactive accounts.
- Index journal_entries(accounting_date,status,fiscal_period_id), journal_entry_lines(account_id,project_id,flat_id), source links(source_module,source_type,source_id), vouchers(type,voucher_date,status).
- Foreign keys for all dimension references; account/group hierarchy cannot cycle (enforce in service).
- Enforce `debit >= 0`, `credit >= 0`, and exactly one positive side per posted line at application validation; DB CHECK constraints where MySQL version supports them.
- Forbid posting an unbalanced entry, to a closed period, or with unapproved document.
- Use unique source event identity and locks to prevent duplicate posting under concurrent retries.
- Never store full sensitive bank credentials; restrict bank account identifiers and private statement files.

## 5. Integration event mapping (illustrative; accountant sign-off required)

| Source event | Example accounting effect | Avoid |
|---|---|---|
| Material GRN accepted | Dr Inventory / Cr GRNI or Accrued Payable | Also treating supplier bill as another inventory expense |
| Supplier invoice matched | Dr GRNI / Cr Supplier AP (plus approved tax/variance lines) | Duplicating inventory asset |
| Supplier payment | Dr Supplier AP / Cr Bank or Cash | Counting payment as new expense |
| Material consumed by project | Dr Project WIP / Cr Inventory | Counting issue and purchase as two project costs |
| Permanent material transfer | Reclassify project dimension/WIP or inventory ownership; balanced transfer | Posting revenue/expense merely because stock moved |
| Contractor approved running bill | Dr Project WIP / Cr Contractor AP (net of explicitly modelled adjustments) | Duplicating contractor expense entry |
| Contractor payment | Dr Contractor AP / Cr Bank or Cash | Counting payment as project cost again |
| General expense recognized | Dr General Expense / Cr AP or Cash, depending on workflow | Allocating automatically to projects |
| Project expense recognized | Dr Project WIP or Project Expense / Cr AP or Cash | Double counting a contractor bill |
| Flat booking deposit | Dr Cash/Bank / Cr Customer Advance Liability (provisional) | Treating every deposit as recognized sales revenue |
| Flat sale recognized | Dr Customer AR / Cr Property Sales Revenue, with separate cost recognition as approved | Assuming booking = revenue recognition |
| Customer installment received | Dr Cash/Bank / Cr Customer AR or Advance Liability | Counting collection as additional revenue |
| Investor capital received | Dr Bank / Cr Investor Capital or Liability, based on legal agreement | Treating investor funding as sales income |
| Investor profit approved | Dr Profit Distribution/Retained Earnings / Cr Investor Profit Payable, subject to approved policy | Paying before approval or recognizing same profit twice |
| Investor profit paid | Dr Investor Profit Payable / Cr Bank | Creating a second profit allocation |
| Customer refund | Reverse/settle customer advance or AR as applicable / Cr Bank | Automatically treating all refunds as expenses |

**Accountant approval needed before production posting:** sale revenue recognition trigger (agreement/registration/handover), project WIP vs cost of sales timing, investor investment legal classification, investor profit distribution classification, tax/VAT/withholding, retention, supplier GRNI policy, refund/late-fee treatment, opening balances, year-end closing. Never hard-code speculative accounting treatment as a finalized business rule.

## 6. Functional screens and premium UX

### Navigation
Accounting Dashboard, Chart of Accounts, Journal Entries, Vouchers, Cash & Bank, Bank Reconciliation, Receivables, Payables, General Ledger, Trial Balance, P&L, Balance Sheet, Cash Flow, Project/Flat Reports, Period Closing, Reconciliation Center, Settings.

### Visual design
Use Phase 1–4 design tokens, typography, sidebar and shared Blade components. Clean light mode, restrained accent colors, subtle borders, readable tabular numbers, sticky table headings, responsive mobile cards, meaningful empty states. No decorative clutter.

### Interaction standards
- Select2 for lists >7 items; remote AJAX lookup for large COA, customer, project, supplier, contractor, investor and transaction lists; 300ms debounce, paginated search, keyboard support.
- Journal voucher composer: dynamic rows, keyboard-first debit/credit entry, instant client-side totals, **server-side** balancing and authorization on submit; row add/remove without reload.
- AJAX create/edit drafts, submit/approve/post/reverse, search/filter, period selection, report drilldown, bank matching and export initiation; no unnecessary full-page refresh.
- Show current state, version, last actor, approval trail, source document and linked journal for every transaction.
- Confirmation modal for posting/reversal/period lock; optimistic locking and duplicate-submit protection.
- Permission-based UI *and* server-side policy; never rely on hidden buttons for security.
- Loading skeletons, toast feedback, inline validation, accessible focus and proper 401/403/409/419/422/500 handling.
- Reports have filters, comparison periods, drilldowns, print-friendly and permissioned CSV/XLSX/PDF exports (queue heavy exports).

## 7. Suggested permissions

`accounting.dashboard.view`, `accounting.coa.view`, `accounting.coa.manage`, `accounting.journal.view`, `accounting.journal.create`, `accounting.journal.submit`, `accounting.journal.approve`, `accounting.journal.post`, `accounting.journal.reverse`, `accounting.voucher.manage`, `accounting.voucher.approve`, `accounting.cashbank.view`, `accounting.cashbank.manage`, `accounting.bankreconciliation.manage`, `accounting.receivables.view`, `accounting.payables.view`, `accounting.ledger.view`, `accounting.reports.view`, `accounting.reports.export`, `accounting.period.lock`, `accounting.period.close`, `accounting.openingbalance.manage`, `accounting.postingrules.manage`, `accounting.reconciliation.run`, `accounting.reconciliation.resolve`.

Separate project-scoped permissions and sensitive company-wide reports. Default no investor/customer access to internal accounting routes. Prefer maker-checker separation for material amounts, with a configurable self-approval policy.

## 8. Sequential Claude Code prompts

### MASTER PROMPT — run first

```text
You are the lead Laravel architect, accounting systems engineer and UX lead for Key One Properties ERP. Read all available Phase 1, 2, 3, 4 and 5 blueprint files and inspect the ACTUAL repository before coding. Phase 5 adds Accounting & Finance Module 16 to the existing application, never a separate app.

Stack: Laravel, MySQL, Blade, Tailwind CSS, jQuery/AJAX, Select2. Premium clean light UI consistent with existing components. All dropdowns with more than 7 choices must be searchable, and large datasets must use remote AJAX search. All suitable in-page actions must update instantly without full reload.

Non-negotiable: double-entry invariants, immutable posted journals, linked reversal entries, unique source-event posting, MySQL transactions/row locks, decimal money, locked fiscal periods, dynamic permissions, project/flat scope, audit trail and reconciliation. Existing Phase 1–4 operational events are the integration sources. Never duplicate costs or revenue.

Before changes, produce: repository compatibility audit, actual schema inventory, existing financial event map, missing business-policy decisions, COA proposal, migration plan, posting rule matrix and integration/test strategy. Clearly separate confirmed requirements from accounting-policy assumptions. Stop before production postings until accountant/client approves recognition policies. Do not claim tests pass without executing them. After each prompt show files changed, commands run, test results, known issues and next steps.
```

### PROMPT 1 — Audit and accounting architecture

```text
Audit all Phase 1–4 modules and their financial events. Identify source-of-truth tables for purchases/GRN, inventory/consumption/transfers, contractor bills/payments, general/project expenses, flat sales/booking/refunds, installments, investor investments/profit approval/payouts. Document which source events already exist and which need a backward-compatible outbox adapter. Design the accounting event lifecycle, canonical source UUID, journal state machine, transaction boundaries, replay/reconciliation and account mapping strategy. Produce an exact ERD and implementation plan; wait for approval on unresolved recognition rules.
```

### PROMPT 2 — Database migrations, COA and periods

```text
Implement safe additive migrations for account groups, chart of accounts, fiscal years, periods, journals, lines, vouchers, cash/bank accounts, source links, posting rules, reconciliation and bank matching. Reuse existing Phase 1–4 tables. Seed a proposed real-estate COA with Assets, Liabilities, Equity, Income, Expenses, Inventory, Construction WIP, Customer Advances, AR, Supplier AP, Contractor AP, Investor Capital/Payables and Cash/Bank. COA mapping is configurable, not a hard-coded universal policy. Add FK/index/unique constraints, factories, permissions and migration tests. Ensure fresh install and upgrade from Phase 4 work.
```

### PROMPT 3 — Double-entry engine and journal vouchers

```text
Implement one central AccountingPostingService and immutable posted journal ledger. Support draft, submit, approve, post, reverse with strict validation: balanced totals, at least two lines, one nonzero side per line, postable active COA accounts, correct fiscal period, required dimensions and idempotency. Use DB transactions and row locks, linked reversal entries, audit trail and concurrency tests. Build responsive AJAX Journal Voucher editor with dynamic lines, instant totals, Select2 remote COA lookup, inline validation, approval history and no full-page reload. Add unit/feature tests for all invariants.
```

### PROMPT 4 — Receipt, payment, contra, cash and bank

```text
Implement Receipt Voucher, Payment Voucher, Contra/Bank Transfer Voucher and Cash & Bank Management. Every approved/postable voucher creates exactly one balanced journal via the central service. Implement cash/bank account register, statement view, transfer flow, bank statement CSV import, secure private storage, reconciliation suggestions with explicit human confirmation, partial matching rules and duplicate statement detection. Build premium AJAX forms, searchable selectors, filters, balance cards and responsive tables. Add permission, overmatch, duplicate posting and reversal tests.
```

### PROMPT 5 — Integration with Phase 1–4 and subledger reconciliation

```text
Implement event adapters and posting rule resolvers for materials GRN, supplier bill/payment/return, project material consumption and permanent transfer, contractor running bill/payment, general/project expense, booking deposit/refund, recognized sale, customer installment/advance/late fee, investor capital, approved profit payable and payout. Do NOT post speculative accounting treatment: require configurable rules approved by accountant/client for revenue recognition, WIP/COGS, investor legal classification, taxes and refunds. Enforce exactly-once source posting and preserve historical links. Build source-event-to-journal drilldown and reconcile operational supplier, contractor, customer and investor subledgers to GL control accounts. Add tests against duplicate cost/revenue and simultaneous posting.
```

### PROMPT 6 — General ledger, AR/AP and reports

```text
Implement General Ledger, Trial Balance, AR/AP reconciliation reports, Profit & Loss, Balance Sheet, Cash Flow (using an explicitly documented cash-flow classification method), Project-wise and Flat-wise Financial Statements. Query posted journal lines with correct date and period semantics, opening/closing balances, account hierarchy, project/flat dimensions and access scope. Use performant indexed queries and pagination. Create premium filterable AJAX dashboards with drilldown to source vouchers, permissioned exports and queued large reports. Validate sample accounting identities: debits=credits, assets=liabilities+equity, trial balance balanced. Show report limitations where recognition policies are pending.
```

### PROMPT 7 — Period closing, controls and audit

```text
Implement fiscal year/period management, controlled locking/closing, opening balances through balanced journals, reopening with explicit authorization and audit reason, posting-rule version history, maker-checker approval policies, sensitive-account access, journal reversal policy and a reconciliation center. Posted entries must never be edited/deleted. Implement exceptions for pending events, unmapped accounts, out-of-balance sources, duplicate source events, unallocated advances and mismatched subledgers. Add security tests for IDOR, permission escalation, closed periods, CSRF, race conditions and audit immutability.
```

### PROMPT 8 — UI polish, integration QA and release

```text
Audit the full Phase 5 UX and all Phase 1–4 integrations. Preserve the premium clean light design, responsive layouts, Select2 for >7 choices, remote AJAX searches, no-reload posting/filtering and consistent loading/error states. Run migrations on clean and upgraded test databases; unit/feature/integration tests, concurrency/idempotency tests, financial reconciliation tests, report accuracy tests, permissions and export tests. Generate realistic seed scenarios without contaminating production. Deliver a release checklist, accountant sign-off matrix, deployment/rollback plan, migration/backfill strategy, exact test output, known limitations and final integration map. Do not mark Phase 5 complete with failing tests or unresolved production accounting policies.
```

## 9. Essential acceptance scenarios

1. Journal 1,000 debit + 1,000 credit posts; 1,000 debit + 999 credit is rejected.
2. Concurrent duplicate source event produces exactly one journal.
3. Posted journal cannot be edited or deleted; authorized reversal creates balanced inverse entry.
4. Locked period rejects all postings, including automated source events.
5. Supplier invoice and payment do not double count project cost.
6. Inventory receipt, project issue and permanent transfer reconcile physical/ownership and financial value without duplicate expense.
7. Partial customer collection reduces AR only once; advance is not automatically sales revenue.
8. Booking cancellation/refund follows approved mapping and leaves traceable reversal/settlement.
9. Investor profit approval and actual payout create distinct accounting events; unauthorized approval rejected.
10. General expenses remain company-level unless an explicitly approved allocation exists.
11. Saleable-area shared cost allocations reconcile to total allocated project cost with controlled rounding.
12. Trial Balance balances; P&L and Balance Sheet derive from posted ledger; report drilldowns reconcile to journal lines.
13. Cash/bank transfer nets to zero across bank accounts (subject to fees) and is posted once.
14. User cannot see out-of-scope project/flat ledgers or private bank statement files.
15. AJAX actions update relevant UI components without full reload; >7 options use searchable selectors.
16. Financial event backlog is reconcilable; missing/unmapped events are visible, not silently discarded.

## 10. Execution and sign-off

1. Add this file to the existing repository beside Phase 1–4 blueprints.
2. Give Claude the MASTER PROMPT, review audit and policy gaps, then run Prompts 1–8 sequentially.
3. Review each migration and posting rule before allowing real financial postings. Commit after each checkpoint.
4. Before live migration, take DB backup, run dry-run/backfill reconciliation and approve opening balances.
5. Obtain accountant/management sign-off for revenue recognition, cost capitalization, investor treatment, taxes and fiscal closing rules.
6. Go live only when every accounting invariant, authorization check and reconciliation acceptance test passes.

**Definition of done:** all five phases operate as one coherent ERP; posted journals are balanced and immutable; operational subledgers reconcile to GL; reports are accurate under approved accounting policies; UI is smooth and consistent; financial operations are secure, auditable and recoverable.
