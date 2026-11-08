# Financial events (accounting-ready contract)

Phase 1 adds the `financial_events` table and `FinancialEventRecorder`. It is an
integration outbox, **not** an accounting ledger: there are no debits, credits, journals,
general ledger, balance sheet or profit and loss in Phase 1. Phase 5 will read pending
events and post balanced `journal_entries` / `journal_lines`.

No Phase 1 action records a financial event. Project budgets and flat list prices are
estimates, not transactions.

## Contract

| Column | Meaning |
| --- | --- |
| `event_uuid` | Unique id of the event |
| `idempotency_key` | `sha256(module, source type, source id, event type, discriminator)`, unique |
| `source_module`, `source_type`, `source_id` | The business record that caused the event |
| `event_type` | One of `FinancialEventTypes::TYPES` |
| `project_id`, `flat_id`, `customer_id` | References for project and flat costing |
| `amount`, `currency` | `DECIMAL(18,2)`, default `BDT`; passed as a decimal string, never a float |
| `effective_at` | Business date for posting |
| `occurred_at` | When it was recorded |
| `payload` | Extra details (installment number, approval reference, allocation basis) |
| `processing_status` | `pending`, `processed`, `failed` (+ `attempts`, `last_error`, `processed_at`) |

Rules:

- Recording the same business action twice returns the existing row (idempotent).
  Use `discriminator` when one source produces several events of the same type
  (for example installment number 1, 2, 3).
- Business columns are immutable after insert and rows cannot be deleted.
  A correction is a new reversing event (e.g. `collection.refunded`, `booking.cancelled`).
- Record the event in the same database transaction as the business change.

## Expected events by phase

| Phase | Event | Source |
| --- | --- | --- |
| 2 | `purchase.received`, `purchase.supplier_payment` | Purchase / supplier payment |
| 2 | `material.transferred`, `material.borrowed`, `material.returned` | Material movement (cost moves between projects with history) |
| 2 | `contractor.bill_approved`, `contractor.payment` | Contractor bill / payment |
| 2 | `expense.general`, `expense.project` | Expense (two scopes) |
| 2 | `cost.shared_allocated` | Shared construction cost allocated by saleable area |
| 3 | `booking.created`, `booking.cancelled`, `sale.confirmed` | Booking / sale |
| 3 | `installment.due`, `collection.received`, `collection.refunded` | Installments and collections |
| 3 | `investment.received`, `investment.withdrawn` | Investor capital (multiple investors per flat, total active share ≤ 100%) |
| 3 | `profit.distribution_approved`, `profit.distribution_paid` | Investor profit, only after authorised manual approval |
