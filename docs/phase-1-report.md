# Phase 1 delivery report

## Delivered

| Area | What works |
| --- | --- |
| Foundation | Laravel 13, MySQL 8, Tailwind 4, jQuery, Select2, navy/gold design system, configurable brand and sidebar colours, reusable Blade components, AJAX helpers (forms, tables, modals, partial reloads) |
| Security | Sign-in by email or mobile, admin-handled password reset requests, 5-attempt / 5-minute lock per IP and per username with live countdown, optional TOTP 2FA with recovery codes and admin control, password reset by email, session revocation, CSP / HSTS / frame headers, random `public_id` in every URL, private file storage, audit log |
| Core | Users, roles and permissions (with escalation guards), departments, activity log with redacted diffs, branding settings, profile, global search |
| Projects | Directory (table and cards), CRUD and archive, stages with reorder, immutable progress updates, stage-average progress, gallery, team, project activity |
| Flats | Buildings and floors, units with dependent selects, bulk create with preview and idempotency, immutable price and status history, unit images |
| Customers | Directory, profile, unique mobile, duplicate warning, encrypted and masked NID with audited reveal, addresses, notes, private documents, honest Phase 3 placeholders |
| Accounting-ready | `financial_events` outbox with idempotency keys and immutable rows; no ledger |
| Operations | Backup command, scheduler, sample data, deployment / backup / UAT docs |

## Verification (run in this environment)

| Check | Result |
| --- | --- |
| Automated tests (`php artisan test`, MySQL) | 104 passed, 570 assertions |
| Migrations | `migrate:reset` then `migrate --seed` succeed |
| Code style (`pint --test`) | passed |
| Asset build (`npm run build`) | passed |
| Browser checks (Playwright, desktop 1440px and iPhone 13) | sign-in lock and countdown, user, role, department, project, stage, progress, gallery, building, bulk create, price change, customer, document flows; no console errors; no horizontal overflow on phone |

Test coverage includes cross-project access through guessed ids, AJAX endpoints, option
sources, image and document URLs; a route sweep that fails if any route is reachable
without signing in or without a permission check.

## Known gaps and decisions to revisit

- Password reset is handled by administrators (request, notification, admin reset, forced change).
  Email-based reset can be added later once SMTP is available.
- Notifications are sent for password reset requests only; other Phase 1 actions do not notify yet.
- `approval_requests` exists as a foundation only; approval workflows arrive with the modules that need them.
- Stage reordering uses up/down buttons (keyboard accessible) rather than drag and drop.
- Images are served at original size; there is no thumbnail generation.
- Accessibility was checked by hand (focus trap, labels, keyboard tabs); no formal WCAG audit.
- The browser checks were run as ad-hoc scripts and are not part of the committed test suite.
- Investor visibility flags are stored on progress updates and images for the Phase 4 portal.
- Admin IP allowlist is deferred until requested.
