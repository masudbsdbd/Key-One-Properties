# Customers

## Access

- `customers.view`: customers the user created. `customers.view_all`: every customer.
- `customers.manage_documents` is needed to list, upload, download or delete documents.
- `customers.view_sensitive` is needed to see or edit the NID / passport number.

## Rules

- `primary_mobile` is unique (normalised to `01XXXXXXXXX`, so `+880 1712-345678` and
  `01712345678` are the same number). The error names the existing customer only when the
  user may see that record.
- A match against another customer's alternate number shows a "possible duplicate" warning
  but does not block saving.
- NID / passport is optional, encrypted at rest (Laravel `encrypted` cast), shown masked
  (`••••6789`), revealed only through an audited action, and never written to the activity log.
- Customer codes are generated: `CUS-00001`.
- Archive is a soft delete and can be restored.

## Documents

Stored on the private `local` disk under `customers/{public_id}/` with random file names.
Allowed: PDF, JPG, PNG, WEBP, DOC, DOCX, XLS, XLSX up to `UPLOAD_DOCUMENT_MAX_KB`
(default 10 MB). Downloads are authorised, sent as attachments and logged.

## Future phases

Bookings and payments tabs show "Coming in Phase 3". No balances or bookings are invented.
Customer portal logins are not created in Phase 1.
