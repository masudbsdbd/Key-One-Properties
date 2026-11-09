# Deployment (shared hosting friendly)

## Requirements

- PHP 8.3 with extensions: pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, fileinfo, gd, zip, intl
- MySQL 8
- Cron access (one entry)
- `mysqldump` and `gzip` on the server for the backup command (optional, see backup doc)

## Steps

1. Upload the code (without `node_modules`, `.env`, `storage/app/private/*`).
2. Point the domain's document root to `public/`. Never expose the project root.
3. Install dependencies and build assets (build locally if the host has no Node):

   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```

4. Create `.env` from `.env.example` and set at least:

   | Key | Value |
   | --- | --- |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `APP_URL` | `https://your-domain` |
   | `DB_*` | database credentials |
   | `SESSION_SECURE_COOKIE` | `true` (HTTPS only) |
   | `LOGIN_PATH` | a non-obvious path instead of `login` |
   | `TRUSTED_PROXIES` | proxy IPs only when behind Cloudflare or a load balancer |
   | `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD` | first admin, used by the seeder only |

5. Run:

   ```bash
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --class=RolesAndPermissionsSeeder --force
   php artisan db:seed --class=SuperAdminSeeder --force
   php artisan db:seed --class=ExpenseCategorySeeder --force
   php artisan storage:link
   php artisan optimize
   ```

6. Remove `SUPER_ADMIN_PASSWORD` from `.env` after the first sign-in and change the password.
7. Make `storage/` and `bootstrap/cache/` writable by the web server only.

## Cron

One entry runs the scheduler, which also processes the database queue:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs: nightly database backup (02:30), weekly activity log clean-up
(older than 365 days), daily expired password-reset clean-up, failed-job pruning,
queue worker every minute, nightly reconciliation of stock, supplier and contractor ledgers
and project cost (03:10, output in `storage/logs/reconcile.log`), and deletion of expired report
exports (04:10, `keyone:prune-report-exports`).

Queued report exports (Reports → Queue) need the scheduler: the per-minute queue worker builds them.
Without cron they stay "Queued". Files are kept for `REPORT_EXPORT_DAYS` days (default 7) under
`storage/app/private/report-exports`, never in `public/`.

Run `php artisan keyone:reconcile` after any data import or restore; it exits non-zero when a
ledger or project cost does not match its source documents.

## Updating

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan optimize
php artisan keyone:reconcile
php artisan up
```

## Security checklist

- HTTPS only; HSTS is sent automatically on HTTPS requests.
- `APP_DEBUG=false`; error pages never show stack traces.
- `LOGIN_PATH` changed from the default.
- Sign-in lock: 5 failures per IP or per username lock for 5 minutes (`LOGIN_MAX_ATTEMPTS`, `LOGIN_DECAY_SECONDS`).
- `TRUSTED_PROXIES` set only to real proxy addresses, otherwise client IPs can be spoofed.
- Private uploads live in `storage/app/private` and are served only through authorised routes.
- Backups are stored outside `public/` and copied off the server.
- Investor and customer portals live at `/investor` and `/customer`. Portal accounts are created from the
  investor or customer page (Portal tab), must be verified by a second user, and never reach internal pages.
- After upgrading, give staff roles the new permissions they need (Reports, Portal Access); the seeder adds
  them to Admin only. Investor and Customer roles are created by the seeder.

## Query review

The main list queries were checked with `EXPLAIN` on sample data: unit lists use
`(project_id, status)` / `(floor_id, status)`, customer lists use `created_by`, project
activity uses `(project_id, created_at)`, progress history uses `(project_id, report_date)`,
and project membership checks use the `project_user` unique key. All lists are paginated
server-side (15 rows by default, 100 max).
