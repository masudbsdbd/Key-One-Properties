# KEY ONE PROPERTIES ERP — PHASE 1 DEVELOPMENT BLUEPRINT
Version 1.0 | November 2026 | For Laravel implementation

> Decisions confirmed by the client after this blueprint was written are listed
> in `CLAUDE.md` under "Confirmed decisions". Where they differ, those decisions win.

## 0. Context and binding decisions
Build a centralized, single-company Real Estate ERP with 16 logical modules delivered over five phases. This document covers Phase 1 only: (1) Core & Access, (2) Project Management, (3) Flat Inventory, (4) Customer Management. Phase 2: construction, materials, suppliers, contractors, expenses. Phase 3: booking, collections, investor. Phase 4: portals/reports. Phase 5: full accounting. The approved proposal also requires responsive branding, activity log/audit trail, backups, project gallery, and later accounting integration. No landowner/developer sharing, flat transfer, or closed-project editing policy in current scope.

Confirmed future integration rules: two expense scopes (general and project); material borrow/return and permanent transfer with project cost movement and history; dynamic approval permissions; multiple investors per flat; initial flat-wise profit share, total active share <=100%; shared construction costs allocated by saleable area; investor profit distribution only after authorized manual approval; investor sees project and flat costs subject to permission; customer portal is read-only; one domain initially, routes isolated so portals can be moved to subdomains later. Fixed-term early withdrawal/interest rules are deferred. Accounting-ready now; full double-entry posting later.

## 1. Non-negotiable technology and UX rules
- Laravel stable version compatible with target hosting, PHP compatible with Laravel version, MySQL 8+, Blade, Tailwind CSS, jQuery AJAX. Use Select2 (or accessible equivalent) for searchable selections. No React, Vue, Livewire, SPA framework.
- Full page navigation is acceptable between main sections; **within a page**, CRUD, inline status changes, modal forms, search/filter/sort/pagination, permission changes, gallery updates and dependent selectors use AJAX/fetch with no forced reload. Use progressive enhancement when JS fails.
- Dropdown with **more than 7 options** MUST be searchable. For 8–100 small static options use client-side search; for large or growing lists use AJAX server-side search, debounce 250–350ms, 20–30 paginated results, minimum 2 typed characters as appropriate. Never load all customers/projects into HTML. Accessible keyboard navigation, clearable options, preselected edit values, dependent selector reset.
- Design: premium restrained light theme, white/off-white backgrounds, deep navy typography, one teal/blue accent, consistent 8px spacing rhythm, 12–16px radii, subtle shadows, generous whitespace, high contrast, consistent skeletons/empty states, mobile responsive. Avoid excessive gradients and crowded dashboards. Sidebar collapsible with persisted preference; topbar with global search/notifications/profile; clear page header and breadcrumb; compact but legible data tables.
- Form validation inline (field errors from HTTP 422), toast success/error, loading indicators, disabled submit while pending, confirmation dialog for destructive actions, optimistic UI only when reversible; otherwise update after server success. Prevent duplicate submission and race conditions. Use 401/403/409/419/422/429/500 error handling and CSRF tokens.
- Strong server-side authorization on every endpoint and record, not only hidden menus. Rate-limit login/search as appropriate, sanitize output, validate upload MIME/size, store private documents outside public disk, audit sensitive changes. Prefer policies + permission middleware. Use transactions and row locks for state changes.
- Shared-hosting friendly: database-backed queue/cache/session as configured; scheduler via cron, no mandatory Redis or persistent websocket worker. Do not claim 100% security.

## 2. Phase 1 navigation and screen inventory
### Common layout
Login, forgot/reset password, optional 2FA foundation, dashboard shell, profile/settings, users, roles, permissions, activity logs, notifications panel, file viewer.
### Projects
Project list (table/cards toggle), create/edit drawer or modal, project detail with Overview / Stages & Progress / Buildings / Flats / Gallery / Team / Activity tabs, project budget summary, stage timeline, gallery uploads, project member permissions.
### Flats
Project > Building > Floor > Flat hierarchy; list with searchable filters, flat creation/edit, bulk create wizard (preview before commit), flat detail, price history, availability history, status management. No bookings/payments in Phase 1; future states can be modeled but must not be activated without workflow.
### Customers
Customer directory, create/edit, profile with contact details, addresses, notes, documents, activity. Future Booking/Payment tabs show honest 'Coming in Phase 3' empty state, never fabricated balances.

## 3. Database conventions
All core tables: BIGINT unsigned primary key (or UUID consistently, choose BIGINT for initial MySQL simplicity), created_at/updated_at; softDeletes where appropriate. Foreign keys + indexes. Financial values DECIMAL(18,2), quantities DECIMAL(18,3), areas DECIMAL(12,2), percentages DECIMAL(7,4), never FLOAT. Monetary currency default BDT (configurable). Store UTC timestamps; render Asia/Dhaka timezone. Every auditable change includes actor ID and before/after diff via activity log with sensitive fields redacted. `status` controlled by validated state transitions; don't use arbitrary free text. Avoid enum DB columns if admin-editable stages are needed. Never store derived counts as authoritative data.

## 4. Phase 1 relational schema (recommended)
Notation: PK=primary key, FK=foreign key, UQ=unique, IDX=index. Exact field names may be adjusted consistently after migration review.

### Identity / access
**users**: id PK, name, email UQ nullable (if business allows), mobile UQ nullable, password_hash (Laravel `password`), status(active/inactive/locked), last_login_at, email_verified_at nullable, remember_token, timestamps, soft_deletes. Require at least one verified login identifier; decide login identifier before coding.
**roles**, **permissions**, **model_has_roles**, **model_has_permissions**, **role_has_permissions**: use Spatie Laravel Permission compatible migrations and guard_name; permissions use names like `projects.view`, `projects.create`, `projects.update`, `projects.manage_members`, `flats.change_price`, `customers.view_all`, `users.manage`, `approvals.approve`, `activity.view`. Never hardcode only role names for access.
**departments**: id, name UQ, status, timestamps.
**department_user**: department_id FK, user_id FK, UQ(department_id,user_id).
**project_user**: project_id FK, user_id FK, membership_role(optional), is_primary boolean, timestamps, UQ(project_id,user_id). Access scoped by project membership or explicit broader permission.
**activity_log**: use Spatie Activitylog, include subject, causer, event, properties, batch_uuid, timestamps; add project_id context if practical/indexed. Audit permission changes and login security events.
**notifications**: Laravel database notifications migration; foundation only.
**approval_requests** (foundation, not fake workflow): id, type, subject_type, subject_id, requester_id, approver_id nullable, status(pending/approved/rejected/cancelled), request_payload JSON, reason, decided_at, timestamps; index(status,type,created_at). Enforce actual per-module approval rules when modules are implemented.

### Projects and progress
**projects**: id PK, code UQ, name, description nullable, location_text, address nullable, latitude/longitude nullable, start_date, target_completion_date nullable, actual_completion_date nullable, estimated_budget DECIMAL(18,2) nullable, status (planned/active/on_hold/completed), cover_media_id nullable, created_by FK users, timestamps, soft_deletes. No authoritative actual expense field yet.
**project_stages**: id PK, project_id FK, name, sequence, status(not_started/in_progress/completed/on_hold), planned_start_at nullable, planned_end_at nullable, actual_start_at nullable, actual_end_at nullable, progress_percent DECIMAL(5,2) default 0, remarks nullable, timestamps; UQ(project_id,sequence), index(project_id,status). Stage names editable by authorized users; seed sensible defaults.
**project_progress_updates**: id PK, project_id FK, stage_id FK nullable, progress_percent DECIMAL(5,2), summary, report_date, recorded_by FK users, timestamps; immutable or audited corrections; index(project_id,report_date).
**project_media**: id PK, project_id FK, uploaded_by FK, disk, path, mime_type, file_size, caption, sort_order, visibility(internal/investor), timestamps. Only show investor-visible media in future portal.

### Building, floors, flats
**buildings**: id PK, project_id FK, code, name, description nullable, status, timestamps; UQ(project_id,code).
**floors**: id PK, building_id FK, floor_number signed integer, label, sort_order, timestamps; UQ(building_id,floor_number). Support ground/basement levels via signed number + label.
**flats**: id PK, project_id FK (denormalized for efficient scoping but validate against building), building_id FK, floor_id FK, flat_no, flat_type nullable, bedrooms nullable, bathrooms nullable, saleable_area DECIMAL(12,2), area_unit default sqft, list_price DECIMAL(18,2) nullable, status(available/unavailable/maintenance; reserve booked/sold for Phase 3), description nullable, created_by FK, timestamps, soft_deletes; UQ(building_id,flat_no) (or UQ(floor_id,flat_no) depending on client numbering); indexes(project_id,status), (floor_id,status). Flat/project/building/floor consistency validated server-side.
**flat_price_history**: id PK, flat_id FK, old_price, new_price, reason, changed_by FK users, effective_at, timestamps. Do not overwrite without history.
**flat_status_history**: id PK, flat_id FK, from_status, to_status, reason nullable, changed_by FK users, changed_at; index(flat_id,changed_at).
**flat_media**: id PK, flat_id FK, disk, path, mime_type, size, caption, sort_order, uploaded_by FK, timestamps.

### Customers
**customers**: id PK, customer_code UQ, full_name, email nullable, primary_mobile, alternate_mobile nullable, date_of_birth nullable, nid_or_passport nullable (encrypt/mask; store only if justified), occupation nullable, status(active/inactive), source nullable, created_by FK users, timestamps, soft_deletes; indexes(primary_mobile), (full_name). Do not assume mobile uniqueness until duplicate policy decided.
**customer_addresses**: id PK, customer_id FK, address_type(present/permanent/other), address_line, city, district, postal_code nullable, country default Bangladesh, is_primary boolean, timestamps.
**customer_notes**: id PK, customer_id FK, body, visibility(internal), created_by FK users, timestamps, soft_deletes.
**customer_documents**: id PK, customer_id FK, category, original_name, disk(private), path, mime_type, file_size, uploaded_by FK, timestamps; no public URL for confidential identity documents.

### Cross-module foundation (NOT full accounting implementation)
**financial_events**: id PK, event_uuid UQ, source_module, source_type, source_id, event_type, project_id nullable FK, flat_id nullable FK, customer_id nullable FK, amount DECIMAL(18,2) nullable, currency default BDT, effective_at, occurred_at, payload JSON, processing_status(pending/processed/failed), timestamps; UQ(source_module,source_type,source_id,event_type,event_uuid) is redundant with event_uuid and may be omitted; design a deterministic `idempotency_key` UQ for retries. This is an integration/outbox record, NOT a journal entry or accounting ledger. Reserve future `journal_entries` and `journal_lines` in Phase 5; do not create fictitious debits/credits.
**file_assets** optional shared attachment metadata if reuse justifies it; avoid redundant file systems.

### Relationships
Project 1—N Buildings, Stages, Progress Updates, Media, Project Users; Building 1—N Floors; Floor 1—N Flats; Flat 1—N Price History and Status History; Customer 1—N Addresses/Notes/Documents; User N—N Projects; User N—N Roles and Permissions. No customer-flat ownership FK in Phase 1; bookings/sales will establish relationship in Phase 3.

## 5. Business rules and permission matrix
- Project stages visible later to investors; mark progress updates as `internal` vs `investor_visible` if needed; never expose sensitive staff-only comments.
- Project progress is a summary from stage updates or an explicitly authorized manual field, not arbitrary inconsistent values. Validate 0–100.
- Flat saleable area > 0. Flat code unique within agreed numbering scope. Price >= 0. All price changes require reason + permission and produce history.
- Phase 1 flats are not yet 'sold' by entering a status manually; booking/sale lifecycle comes in Phase 3.
- Customers may exist without a booking; customer portal login is future Phase 4, not automatically provisioned for every customer in Phase 1.
- Permission groups: users/roles/departments; projects CRUD, stages, progress, media, members; buildings/floors/flats CRUD, bulk_create, price_change, status_change; customers CRUD, documents, view_all; activity view; approvals request/approve/reject; dashboard view. Separate `view_own`, `view_assigned_project`, `view_all` where necessary. Deny by default; super admin via explicit policy.
- Test user with access to Project A cannot access Project B through guessed ID, AJAX endpoint, attachment URL, or export.

## 6. AJAX contract
- JSON response success: `{ "success": true, "message": "...", "data": {...}, "meta": {...} }`.
- Validation errors: HTTP 422 `{ "message":"Validation failed", "errors":{"field":["..."]} }`.
- Unauthorized 401, forbidden 403, CSRF expired 419, stale edit/conflict 409, rate limited 429.
- Server-side pagination, sorting, filtering; whitelist sort keys; stable query parameters. Select2 endpoints return `{ "results": [{"id": 123, "text": "..."}], "pagination": {"more": true} }`; authorization filters all results.
- CSRF meta tag; jQuery global AJAX setup; abort stale search requests; button pending state; update only affected DOM component after success; modal focus management; accessibility announcements; no global page reload.
- Use transactions for multi-table writes, idempotency keys for retry-prone actions, and enforce business invariants in services + database constraints where possible.

## 7. Master prompt
```text
You are the principal Laravel architect and senior product designer implementing Key One Properties ERP Phase 1. Read KEY_ONE_PHASE_1_BLUEPRINT.md completely and treat it as the binding product specification. Build only Phase 1 modules: Core & Access, Projects, Flats, Customers, plus shared UX/accounting-ready foundations. Future modules must not be silently implemented. Use Laravel + Blade + Tailwind + jQuery AJAX + MySQL; no React/Vue/Livewire. Design an exceptionally polished, clean, accessible LIGHT UI that feels immediate and cohesive. Searchable Select2-style dropdown whenever options >7; remote paginated search for large lists. All in-page CRUD/filter/inline actions via AJAX with no full page reload. Preserve permission checks on server, validation, audit logs, transactional updates and tests.
First inspect the repository, PHP/Laravel versions and existing files; do not overwrite work. Propose a file/folder plan and migrations before editing. Work in small verifiable increments. For every increment: show files changed, migration implications, commands, tests run, and any unresolved decisions. Never invent completed functionality or test results. Use responsive layouts, reusable Blade components and accessible dialogs. Avoid hardcoded fake dashboard financial values.
```

## 8. Sequential implementation prompts
### Prompt 1 — Repository foundation and design system
```text
Implement only the foundation of Phase 1. Inspect existing code and install/configure compatible Laravel, Blade, Tailwind, jQuery, Select2, MySQL, Spatie Permission and Activitylog (only if compatible). Create app shell, premium light login page, responsive sidebar/topbar, breadcrumb, page heading, stat card, data table, form fields, searchable select, modal/drawer, confirmation dialog, toast, skeleton and empty state. Build shared AJAX helper with CSRF, error handling, pending states and accessible focus. Define design tokens and component usage docs. Do not build business CRUD yet. Add component demo/showcase route protected for development. Run lint/build/tests and report.
```
### Prompt 2 — Database schema and access
```text
Implement Phase 1 migrations exactly as reviewed in the blueprint, resolving any inconsistencies before running. Include indexes, FKs, constraints, soft deletes, seeders for roles/permissions, and realistic non-sensitive sample data. Implement login/logout/password reset, user profile, user CRUD, departments, roles/permission management, project-scoped policies, and activity logging. AJAX actions update tables and permissions without reload. Security tests must prove denied users cannot call endpoints directly. Do not implement investor/customer portals yet.
```
### Prompt 3 — Project Management
```text
Build Projects module: searchable/filterable project directory, create/edit/archive (policy gated), detail with overview, stage timeline, progress updates, budget estimate, gallery, team assignments, and activity. AJAX CRUD, Select2 for large user/project selectors, image upload validation and authorization. Stages are reorderable and have progress/status history; project overall progress calculation must be documented. Test project access isolation and progress validation. No construction cost ledger yet.
```
### Prompt 4 — Flat Inventory
```text
Build Building > Floor > Flat hierarchy under Projects. Implement flat listing, filtering, bulk-create wizard with validation/preview, flat detail, price updates with immutable price history, status history, area validation and media. Use dependent AJAX Select2 for project/building/floor selectors, avoid full reloads. Do not allow manually marking booked/sold; that belongs to Phase 3. Implement unit tests for duplicate flat numbers, cross-project FK consistency, invalid prices and permissions.
```
### Prompt 5 — Customer Management
```text
Build customer directory and profiles, create/edit/archive, addresses, internal notes, secure private document upload/download, activity timeline, server-side search/filter. Mobile duplicate policy is not yet decided: warn about possible duplicates without blocking unless business rules later require it. No customer booking/payment data yet; show truthful future-module placeholders. Test private document access, unauthorized customer access and AJAX validation.
```
### Prompt 6 — Accounting-ready integration and hardening
```text
Implement a minimal cross-module financial event/outbox contract with unique idempotency keys and project/flat/customer references, but do NOT implement journal posting, GL, balance sheet or pretend that financial accounting is complete. Document events expected from later sales, purchasing, transfers, expenses and investor approvals. Add integration tests, indexes/query plan review, pagination performance, authorization regression tests, upload security, backup/restore runbook, deployment .env example, scheduler/queue notes and a Phase 1 UAT checklist. Run tests and provide a precise remaining-work report.
```

## 9. Acceptance criteria
1. Phase 1 screens responsive at mobile/tablet/desktop, accessible keyboard operation, consistent visual design.
2. Every dropdown with >7 choices is searchable; large lists remote-paginated; edit selections load correctly.
3. No full reload for in-page CRUD, filters, pagination, price/status change, member assignment, upload list refresh.
4. 422 errors display inline, unauthorized actions return 403, stale edits 409 where applicable, duplicate submissions prevented.
5. All project data filtered by project access on every server query, including AJAX search and attachments.
6. Price/status changes and sensitive access changes auditable.
7. Project stages and progress are trackable and future investor portal integration is documented.
8. Financial events are accounting-ready but not misrepresented as double-entry accounting.
9. Migrations up/down, seeders, automated tests, build and setup instructions pass in actual environment.
10. Genuine test results and any scope gaps are reported instead of silently assuming requirements.

## 10. Clarifications to resolve during implementation (not permission to invent)
- Login by email, mobile or both; whether 2FA is mandatory.
- Flat numbering uniqueness within building vs floor; choose one explicitly before migration.
- Whether a customer mobile may appear on multiple profiles; default: duplicate warning, no uniqueness constraint.
- Project overall progress weighted by stages vs admin-entered; default: stage-based average only if client approves.
- Branding logo, official domain, address format, upload size limits and user/role seed accounts.

## 11. Suggested directory structure
app/Modules/Core/{Controllers,Requests,Services,Policies}; app/Modules/Projects/...; app/Modules/Flats/...; app/Modules/Customers/...; app/Support/{Ajax,Authorization,FinancialEvents}; resources/views/{layouts,components,core,projects,flats,customers}; resources/js/{ajax,components,pages}; routes/{web,core,projects,flats,customers}. Laravel conventions and auto-discovery must be configured intentionally; avoid inventing a module loader when ordinary namespaces and route includes suffice.

## 12. Delivery checklist
- [ ] migrations and ERD
- [ ] roles and permissions seed
- [ ] premium reusable UI kit
- [ ] Core CRUD
- [ ] Project CRUD/stages/gallery
- [ ] Building/Floor/Flat CRUD and histories
- [ ] Customer CRUD/private files
- [ ] AJAX search/filter and searchable selects
- [ ] audit and security tests
- [ ] financial integration contract
- [ ] installation/deployment guide
- [ ] UAT sign-off checklist
