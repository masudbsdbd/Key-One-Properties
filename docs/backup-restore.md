# Backup and restore

## What to back up

1. The MySQL database.
2. `storage/app/private` (customer documents, project and unit images).
3. `storage/app/public/branding` (logo and favicon).
4. The `.env` file (store it separately and securely; it holds `APP_KEY`).

`APP_KEY` encrypts customer NID / passport numbers, 2FA secrets and sessions. A database
backup restored with a different `APP_KEY` cannot decrypt those fields. Keep the key with
the backups, in a separate secure place.

## Automatic database backup

`php artisan keyone:backup` writes `storage/app/private/backups/<db>-<timestamp>.sql.gz` and
keeps the latest 14 (`--keep=`). The scheduler runs it nightly at 02:30. It needs `mysqldump`
on the server. If the host does not allow it, use the hosting panel's scheduled backups.

Copy backups off the server regularly (hosting panel, rsync or cloud storage).

## Files

```bash
tar -czf keyone-files-$(date +%F).tar.gz storage/app/private storage/app/public/branding
```

## Restore

1. Put the application in maintenance mode: `php artisan down`.
2. Restore `.env` with the original `APP_KEY`.
3. Restore the database:

   ```bash
   gunzip < keyone-YYYYMMDD-HHMMSS.sql.gz | mysql -u USER -p DATABASE
   ```

4. Restore files:

   ```bash
   tar -xzf keyone-files-YYYY-MM-DD.tar.gz
   ```

5. Run `php artisan migrate --force` (only if the code is newer than the backup),
   `php artisan optimize:clear`, then `php artisan up`.
6. Sign in and check a customer's NID reveal and a document download to confirm the key and files.

Test a restore on a separate database at least once per quarter.
