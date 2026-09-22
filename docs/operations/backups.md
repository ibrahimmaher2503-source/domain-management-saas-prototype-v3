# Backups and restore drill

The production MySQL or MariaDB database is the source of truth for users, orders, payments, domains, provider operation records, reconciliation state, sessions, cache, and queued jobs. Backups must be encrypted, stored outside the application host, and retained according to the organisation's retention policy (minimum: daily backups for 14 days plus a weekly backup for 12 weeks).

## Daily backup

Use the managed database provider's encrypted point-in-time/daily backup facility where available. Otherwise, run a scheduled `mysqldump --single-transaction --quick` with credentials supplied outside the command line, upload the dump to separate encrypted object storage, and record the checksum and completion time. Never write dumps under `public/` or commit them.

The backup job must alert on failure, insufficient storage, or an age beyond 24 hours. Keep at least one recent backup in a different failure domain from the application host.

## Restore drill

At least monthly, restore the newest backup into an isolated MySQL or MariaDB instance, verify the checksum, run migrations in dry-run/review mode, and execute the application test/preflight checks against the restored database. Verify representative users, paid orders, domains, payments, and unresolved provider operations. Record the restore duration and result; do not point production at the drill database.

## Incident restore

1. Freeze writes or place the application in maintenance mode.
2. Identify the restore point and preserve the current database/queue evidence.
3. Restore to a new database instance; validate schema and application connectivity.
4. Run `php artisan app:preflight`, inspect unresolved operations, and reconcile provider state using the read-only sweep.
5. Switch the connection secret/configuration, restart workers, probe `/health/ready`, and remove maintenance mode only after validation.

Provider operations can be externally durable even when local state is restored. Do not blindly replay paid provisioning writes after a restore; use the recorded operation status and the reconciliation jobs to confirm provider state first.
