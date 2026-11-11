# Phase 5 UAT checklist

Tick each line on staging. Sign in as a user with the Accountant role unless a line says otherwise.

## Setting up

- [ ] Cash & Bank → New Account: add a cash account, two bank accounts and a mobile wallet with opening balances.
- [ ] Each account card shows its balance; the four tiles add up to the total.
- [ ] Accounting Settings → Opening Balances: enter a fixed asset and a loan; the page shows the amount that goes
      to Opening Balance Equity; the Balance Sheet still balances.
- [ ] Accounting Settings → switch Automatic Posting on with a start date; the message says how many were posted.
- [ ] Reconciliation → every line reads "Matches" and nothing is waiting.

## Cash and bank

- [ ] Deposit into a bank account from the cash account: both balances change by the same amount.
- [ ] Deposit "Owner's money put in": the bank balance rises and Owner's Capital shows it.
- [ ] Withdraw "Bank charge" and "Labour wages": the statement shows each line with the running balance.
- [ ] Transfer between two banks with a charge: one journal, the charge shown as its own line.
- [ ] Withdrawing more than the cash account holds is refused with the balance in the message.
- [ ] Cancel a transaction with a reason: the balance returns and the line reads "Cancelled".
- [ ] An account with transactions offers no Delete; making it inactive is refused while it holds a balance.

## Payments in the other modules

- [ ] Supplier payment: the account list follows the method (Bank shows only bank accounts).
- [ ] Supplier payment with tax deducted: the bank falls by the amount less tax; Tax Payable rises by the tax.
- [ ] With the books on, a payment without an account is refused.
- [ ] Each payment page shows "In the Accounts" with its journal number; the journal opens from there.
- [ ] A payment dated in a locked month is refused with the month named.

## Cheques

- [ ] Customer payment by cheque, status In Hand: after posting, Cheques shows it under In Hand and the bank
      balance has not changed.
- [ ] Deposit, then Cleared: the bank balance rises on the clearing date.
- [ ] Bounced: the payment is reversed and the customer's dues return.
- [ ] Enter a cheque that is already Cleared: it asks for the bank account and goes straight to it.
- [ ] Enter a cheque that already Bounced: it is kept on record and does not count.

## Journals

- [ ] New Journal: typing a debit clears the credit of the same line; the difference shows in red until zero.
- [ ] Submit for Approval, then Approve & Post as a second user; the maker cannot approve their own journal.
- [ ] A posted journal offers Reverse only; the reversal links back and forth.

## Reports

- [ ] Accounting overview: the four figures and six report cards are visible without scrolling on a laptop.
- [ ] Profit & Loss: change the period and the project; the page does not reload; "Compare" adds a column.
- [ ] Click an account in Profit & Loss: the General Ledger opens for that account and period.
- [ ] Balance Sheet shows "Assets equal liabilities plus equity".
- [ ] Cash Flow: the closing figure equals the Cash & Bank total.
- [ ] Project Financials: click a project to see its units.
- [ ] Export each report as CSV, PDF and Print.

## Months and years

- [ ] Lock last month; a journal dated in it is refused; unlock asks for a reason and shows it.
- [ ] Close a finished fiscal year: Retained Earnings receives the profit; the year's Profit & Loss still shows it.
- [ ] Reopen the year with a reason: the closing entry is reversed.

## Bank reconciliation

- [ ] Bank account → Reconcile → enter the statement date and balance → tick entries: the difference updates
      with each tick; Complete is offered only at zero.
- [ ] A completed reconciliation cannot be changed; the next one offers only entries not ticked before.

## Security

- [ ] A user without accounting permissions sees no Accounting menu and gets 403 on every accounting URL.
- [ ] Accounts Viewer can open reports and journals but sees no post, approve, lock or transfer buttons.
- [ ] A user limited to assigned projects cannot filter a report by another project.
- [ ] Investor and customer portal accounts cannot open any `/accounting` page.
