# Phase 5 decisions (Accounting & Finance)

Blueprint: `KEY_ONE_PHASE_5_BLUEPRINT.md`. **Status: not started.** The client asked to start later (2026-11-11);
nothing in this file is built yet. Do not begin Phase 5 until the client says so.

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

No open questions remain. Phase 5 still waits for the client's word to start.
