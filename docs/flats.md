# Flat inventory

Hierarchy: Project > Building > Floor > Unit (flat). `flats.project_id` is stored for fast
scoping and is always set from the floor's building on the server, never from the request.

## Rules

- Unit number is unique within a building (`UQ(building_id, flat_no)`), case-insensitive,
  including archived units.
- Saleable area must be greater than 0. Price must be 0 or more. Money is `DECIMAL(18,2)`.
- A unit can move to another floor of the same building only.
- Price changes go through the price action only (`flats.change_price`), need a reason and write
  an immutable `flat_price_history` row. The edit form refuses `list_price`.
- Status can be set by hand to available, unavailable or maintenance (`flats.change_status`).
  `booked` and `sold` exist in the schema but are reserved for the Phase 3 sales workflow;
  a unit in either state cannot change status by hand or be deleted.
- Every status change writes an immutable `flat_status_history` row.
- Price and status changes lock the unit row (`SELECT ... FOR UPDATE`) inside a transaction.

## Bulk create

1. Choose building, floors, units per floor and a number pattern.
   Tokens: `{floor}` (B1 for basement 1, G for ground), `{n}` (1, 2, 3) and `{L}` (A, B, C).
2. Preview lists every unit number and flags existing or repeated numbers.
3. Create is enabled only when the preview has no conflicts. The server re-checks, creates all
   units in one transaction, and ignores a repeated submit with the same idempotency key.

Maximum 500 units per batch.

## Floors

Floors use signed numbers: negative for basements, 0 for ground. Default labels are
generated (Basement 1, Ground Floor, 1st Floor...) and can be renamed. A floor or building
with units cannot be deleted.
