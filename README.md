# Key One Properties ERP

Single-company real estate ERP. Phase 1 covers Core & Access, Projects, Flat Inventory and
Customers. See `KEY_ONE_PHASE_1_BLUEPRINT.md` for the specification and `CLAUDE.md` for the
16-module roadmap and confirmed decisions.

## Stack

PHP 8.3, Laravel 13, MySQL 8, Blade, Tailwind CSS 4, jQuery, Select2, Vite.
Spatie Permission and Activitylog, Google2FA (TOTP).
No Redis or websocket server is required: cache, session and queue use the database.

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Set `DB_*` and `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` in `.env`, then:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

With `APP_ENV=local` the seeder also loads sample projects, units and customers, and the
UI component showcase is available at `/dev/components`.

## Tests

Tests run against a MySQL database named `keyone_test` (see `phpunit.xml`).

```bash
php artisan test
```

## Documentation

| File | Topic |
| --- | --- |
| `docs/deployment.md` | Shared hosting deployment, cron, environment, security checklist |
| `docs/backup-restore.md` | Backups and restore runbook |
| `docs/erd.md` | Phase 1 entity relationship diagram |
| `docs/ui-components.md` | Blade components and JavaScript contracts |
| `docs/projects.md` | Project access and progress calculation |
| `docs/flats.md` | Flat inventory rules and bulk create |
| `docs/customers.md` | Customer rules, identity data and documents |
| `docs/financial-events.md` | Accounting-ready event contract |
| `docs/uat-checklist.md` | Phase 1 user acceptance checklist |
| `docs/phase-1-report.md` | Delivered scope, test results and remaining work |
