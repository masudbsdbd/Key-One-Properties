# Phase 3 UAT checklist

Use two accounts for every approval: the person who records a document cannot approve it.

## Full journey

- [ ] Available unit → booking → approve → unit shows Booked on the availability board.
- [ ] Receive booking money → post → money receipt prints.
- [ ] Convert to sale → approve → unit shows Sold; the customer statement shows the contract.
- [ ] Create an installment plan (preview first) → approve; total equals contract minus booking money.
- [ ] Receive a partial payment → the preview shows the oldest installment first → post.
- [ ] Let an installment pass its due date → it shows in Dues aging; charge late fees; waive one.
- [ ] Receive more than due → the rest shows as unapplied advance and is used by the next installment.
- [ ] Add two investors to the unit (60% and 30%); a third at 20% is refused by the gauge and on save.
- [ ] Record and post capital for both investors.
- [ ] Tag a project expense to the unit; run cost allocation → worksheet and sources match; approve.
- [ ] Calculate unit profit → shows Provisional while the policy is not set.
- [ ] Set the profit policy in Settings → approve the calculation → investor payables appear.
- [ ] Request a distribution → approve → pay → payout voucher prints; the investor ledger shows it.
- [ ] Run `php artisan keyone:reconcile` → every check matches.

## Sales

- [ ] Two people book the same unit at the same time → only one booking can be approved.
- [ ] Cancel a sale → approve → unit is Available again; refund request is separate and capped.
- [ ] Price change request → approve → unit price history shows requester and approver.
- [ ] Registration and handover stages save with files.

## Collections

- [ ] Reschedule a plan with a paid installment → paid history stays; new lines replace only the open part.
- [ ] Reverse a posted payment → receipt shows VOID; allocations reverse; statement balances.
- [ ] Double-click Record Payment → only one payment is created.

## Investors

- [ ] Amend an agreement → new version is a draft; the old one stays active until approval.
- [ ] Return capital above what was invested → refused.
- [ ] Recalculate profit with more cost → only the difference is posted to payables.
- [ ] A recalculation below what an investor was already paid → approval refused.
- [ ] Reverse a payout → the distribution is Approved again; the ledger shows the reversal.

## Security and UI

- [ ] A user without project membership sees none of the project's bookings, payments, agreements or runs.
- [ ] Every icon button shows a tooltip; disabled buttons show why on hover.
- [ ] Units list: switch to Cards, sign out and in → Cards is still selected.
