# Phase 4 UAT checklist

Tick each line on staging with real-looking data. Use two browsers (staff and portal) side by side.

## Portal accounts

- [ ] Investor page → Portal tab → create account; the account cannot sign in until a second user verifies it.
- [ ] Verified investor signs in by email or mobile and lands on `/investor`; a customer lands on `/customer`.
- [ ] A portal account opening any internal page (e.g. `/projects`) is sent back to its own portal.
- [ ] Revoke the link: the open portal session ends on the next click.
- [ ] Reset the portal password: the user must change it at next sign-in.
- [ ] Grant "project financials" on one project with an expiry date; it disappears after expiry.

## Investor portal

- [ ] Overview shows capital, committed, paid and approved-but-unpaid profit; draft and submitted profit are labelled Estimated / Pending approval.
- [ ] Investments list shows only own agreements; another investor's agreement URL answers "not found".
- [ ] Investment detail: timeline, capital entries, payouts, shared files; download agreement and payout vouchers.
- [ ] Projects: progress and published photos only; financials appear only with a grant, and hidden cost categories are absent (not zero).
- [ ] Statement: filter by unit, export CSV/PDF; totals match the investor ledger in the admin panel.

## Customer portal

- [ ] Overview: contract, paid, balance, overdue and next installment for each unit; booking money counts as paid.
- [ ] Installments: schedule with paid/partly paid/overdue states; rescheduled rows dimmed; late fees and waivers listed.
- [ ] Payments: posted and reversed payments only; receipt PDF opens for posted payments.
- [ ] Statement: export matches the admin customer statement line for line.
- [ ] Phone (360 px): no sideways scrolling on any page.

## Executive dashboard and reports

- [ ] `/executive` widgets load one by one and follow the project and period filters; a user without a module permission does not see that widget.
- [ ] Each report opens, filters, paginates and exports CSV/PDF/print; the address bar keeps the filters.
- [ ] Project budget report actual cost equals the project page "Actual cost" and the construction cost tab.
- [ ] Sales register and collection receipts totals equal the Sales and Customer Payments lists for the same filters.
- [ ] Queue an export → My Exports shows Queued, then Ready within a minute; download works; another user cannot download it.
- [ ] Remove `reports.export` from the requester: the ready file no longer downloads.

## Record pages

- [ ] Unit page shows buyer, contract, paid, balance, milestones, installments, payments, ledger and investors.
- [ ] Customer page Sales and Payments tabs list only that customer's records.
- [ ] Project page shows actual cost and remaining budget (users with cost permission only).
- [ ] Project, unit, customer, supplier, contractor and investor references in lists open their pages; users without access see plain text.

## Security

- [ ] `php artisan keyone:reconcile` passes after UAT.
- [ ] No internal ids in portal URLs or AJAX responses (browser dev tools → Network).
- [ ] Activity log shows portal account changes, grants, report exports and downloads.
