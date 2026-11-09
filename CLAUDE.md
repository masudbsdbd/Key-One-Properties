# Project rules for Claude

Follow these rules in every session on this repository.

## Git identity

- Author and committer: `sadi-tanvir <htanvir.sadi@gmail.com>`
- Set it repo-locally (`git config user.name` / `user.email`); never edit global config.

## Commit signing (Verified badge)

- Every commit must be SSH-signed. Set repo-locally:
  `gpg.format=ssh`, `commit.gpgsign=true`, `gpg.ssh.program=/usr/bin/ssh-keygen`,
  `user.signingkey=<path to public key>`.
- The container is ephemeral. At the start of each session, if no signing key
  exists, generate a new ed25519 key pair (`ssh-keygen -t ed25519 -C htanvir.sadi@gmail.com`),
  then give the user the **public key** and its **SHA256 fingerprint**.
  The user adds the public key on GitHub (sadi-tanvir account, type "Signing Key").
- Run a local sign test (`git log --show-signature`) before the first push.
- Install `openssh-client` if `ssh-keygen` is missing.

## No AI trace

- No Claude/AI name, signature, `Co-Authored-By`, or session link anywhere:
  not in commit messages, author/committer fields, code comments, or files.

## Push

- Push over HTTPS to `origin main` (this container cannot reach GitHub over SSH).
- Fetch `origin main` before pushing. If push returns 403, tell the user to grant
  repository access in the Claude GitHub App; do not retry blindly.

## Workflow

- Work in small, focused features: one feature = one commit = one push.
- Conventional Commits style: `feat:`, `fix:`, `refactor:`, `chore:`, `docs:`, `style:`, `test:`.
- Commit messages in English, short and specific.
- Do not commit code until the user gives the project/feature prompt.

## UI copy

- No explanatory or instructional text in the UI. Do not describe what a button,
  field, page or section does ("Click here to...", "This page lets you...",
  "Use this form to..."). Labels, titles and button text speak for themselves.
- Keep copy short and businesslike: page title, field label, button verb.
  Empty states get a short title and an action, no tutorial paragraphs.
- Field hints only when a format is truly required (e.g. "JPG or PNG, max 5 MB").
- Code comments only where the logic is not obvious; no narrating comments.
- Every clickable element shows `cursor: pointer` (global rule in `resources/css/app.css`);
  disabled controls show `not-allowed`. New clickable patterns must be added to that rule.
- Tooltips: every icon-only button or link has a short `aria-label` ("Edit 10A", "Change status");
  the global tooltip (`resources/js/components/tooltip.js`) shows it on hover and keyboard focus.
  Other elements can use `data-tooltip="..."`. Do not use the native `title` attribute.
- Disabled controls explain why: every control that can be disabled carries a short
  `data-disabled-reason` ("Preview the units first", "Select building first"), shown on hover.
  Dependent selects get one automatically from the parent field's label.

## Security rules

### Login protection
- 5 failed logins from one IP lock that IP out for 5 minutes. Also lock per username.
- Login page shows a countdown of the remaining lock time. The server sends
  `retry_after` seconds and enforces the lock; the client only displays it.
- Read the client IP only from trusted proxies; never trust a raw `X-Forwarded-For`.

### Two-factor authentication
- TOTP (RFC 6238), works with Google Authenticator. QR setup plus backup codes.
- Admin controls 2FA: require it per user or role, reset or disable a user's 2FA.
- 2FA is optional for everyone for now (admin can require it later). TOTP secrets are stored encrypted.

### No sensitive data in URLs
- Never expose a table's internal `id` in URLs, API responses, or forms.
- Each table keeps an internal integer `id` and a random, unique `public_id`
  (UUID or nanoid). Only `public_id` (or slug + `public_id`) leaves the server.
- Every lookup by `public_id` checks server-side authorization (prevents IDOR).
  Do not use Hashids or encoded integer ids.
- No tokens, emails, phone numbers, reset codes, file paths, roles, or debug
  flags in URLs. Files are served through signed, expiring URLs.

### Injection and web attacks
- All database access goes through the ORM or parameterized queries.
  No string-concatenated SQL anywhere.
- Validate all input on the server with schemas. Escape all output (XSS).
- CSRF protection on every form. Security headers: CSP, HSTS, X-Frame-Options.
- Errors never show stack traces, SQL errors, or framework versions to users.

### Accounts and sessions
- Hash passwords with Argon2id or bcrypt. Enforce a minimum length.
- Cookies are httpOnly, Secure, SameSite. Rotate the session id after login.
  Idle timeout and "log out of all devices" support.
- Role-based access control (admin, agent, customer) enforced in middleware.

### Operations
- Admin panel lives on a non-obvious path, not `/admin`.
- Audit log for logins, failed logins, password and 2FA changes, admin actions.
- Rate limit public endpoints (search, contact form). Captcha against bots.
- File uploads: check type and size, random filename, store outside web root.
- Secrets live in `.env`, never in the repo. Scan dependencies for vulnerabilities.
- Deferred: admin IP allowlist. Do not implement until the user asks.

## Product roadmap (Key One Properties ERP)

Single-company real estate ERP: 16 modules over 5 phases. Build only the phase
the user is currently on; never silently implement a future module. Keep
schemas and code ready for later phases (routes isolated, accounting-ready).

### Phase 1: Foundation & Master Data
1. Core & Access Management
2. Project Management
3. Flat Inventory Management
4. Customer Management

### Phase 2: Construction & Operations
5. Construction Management
6. Material & Warehouse Management
7. Supplier & Purchase Management
8. Contractor Management
9. Expense Management

### Phase 3: Sales & Investment
10. Booking & Sales Management
11. Installment & Collection Management
12. Investor Management

### Phase 4: Portals & Reports
13. Investor Portal
14. Customer Portal
15. Dashboard & Reports

### Phase 5: Accounting & Finance
16. Accounting & Finance: double-entry accounting, general ledger, vouchers,
    balance sheet, profit & loss, cash flow

## Confirmed decisions (override the blueprint where they differ)

- Spec: `KEY_ONE_PHASE_1_BLUEPRINT.md` … `KEY_ONE_PHASE_4_BLUEPRINT.md`.
  Implementation decisions live in `docs/decisions/phase-2.md`, `phase-3.md` and `phase-4.md`.
- Current phase: Phase 4 (Investor Portal, Customer Portal, Dashboard & Reports). Phase 3 is complete.
- Portals live at `/investor` and `/customer`; internal pages keep their paths (the admin panel is not on `/admin`).
- Phase 3 policies (client, 2026-11-09): revenue recognised after sale approval when an admin approves the
  profit calculation; loss gives investors zero; refunds have no deduction by default but the approver may
  deduct a percentage; late fees default 0 and count as unit revenue; payment order oldest due first, fee
  before principal; investors share by percentage only; payment methods cash, bank, cheque, mobile banking.
  Cost policy (client, 2026-11-09): unit-tagged project expenses are direct, all other project cost is shared
  by saleable area; material, labour and contractor cost are not unit-taggable for now.
- Stack: PHP 8.3, Laravel 13, MySQL 8, Blade, Tailwind, jQuery AJAX, Select2.
- IDs: BIGINT internal `id` never leaves the server. URLs, AJAX payloads and
  Select2 results use the random `public_id`. Business codes are display-only.
- 2FA: TOTP available but not mandatory for any role yet.
- Login: by email or mobile (both supported).
- Password reset (for now): no email reset. The forgot-password page sends a request to
  administrators (`users.reset_password`); an admin sets a new password and the user must
  change it at next sign-in.
- Flat number unique within a building: UQ(building_id, flat_no).
- Customer `primary_mobile` is unique.
- Customer NID/passport: optional field, stored encrypted, shown masked.
- Project overall progress: average of stage progress.
- Branding: admin can upload logo and favicon and set the app name in settings.
- Uploads: images max 5 MB, documents max 10 MB.
- Super admin seed: `admin@gmail.com`; password comes from `.env`, never the repo.
- Workflow: do not stop between blueprint prompts; keep committing small
  features. Stop only when a decision from the user is truly required.
- UI design samples will come from the user; keep design tokens centralized so
  the look can be swapped without touching business code.
